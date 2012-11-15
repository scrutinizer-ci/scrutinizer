<?php

namespace Scrutinizer\Analyzer\PHP;

use Scrutinizer\Util\XmlUtils;

use Monolog\Logger;
use Scrutinizer\Analyzer\LoggerAwareInterface;
use Scrutinizer\Model\Comment;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;
use Scrutinizer\Model\File;
use Scrutinizer\Config\ConfigBuilder;
use Scrutinizer\Analyzer\FileTraversal;
use Scrutinizer\Model\Project;
use Scrutinizer\Analyzer\AnalyzerInterface;

class MessDetectorAnalyzer implements AnalyzerInterface, LoggerAwareInterface, \Scrutinizer\Analyzer\FilesystemAwareInterface, \Scrutinizer\Analyzer\ProcessExecutorAwareInterface
{
    private $logger;
    private $executor;
    private $fs;

    public function setLogger(Logger $logger)
    {
        $this->logger = $logger;
    }

    public function setProcessExecutor(\Scrutinizer\Util\ProcessExecutorInterface $executor)
    {
        $this->executor = $executor;
    }

    public function setFilesystem(\Scrutinizer\Util\FilesystemInterface $fs)
    {
        $this->fs = $fs;
    }

    public function scrutinize(Project $project)
    {
        FileTraversal::create($project, $this, 'analyze')
            ->setLogger($this->logger)
            ->setExtensions($project->getGlobalConfig('php_md.extensions'))
            ->traverse();
    }

    public function getName()
    {
        return 'php_md';
    }

    public function buildConfig(ConfigBuilder $builder)
    {
        $builder
            ->info('Runs the PHP Mess Detector (http://phpmd.org).')
            ->globalConfig()
                ->arrayNode('extensions')
                    ->defaultValue(array('php'))
                    ->prototype('scalar')->end()
                ->end()
            ->end()
            ->perFileConfig('array')
                ->addDefaultsIfNotSet()
                ->children()
                    ->arrayNode('rulesets')
                        ->defaultValue(array('codesize'))
                        ->requiresAtLeastOneElement()
                        ->prototype('scalar')
                            ->info('A built-in ruleset, or a XML filename relative to the project\'s root directory.')
                            ->beforeNormalization()
                                ->ifTrue(function($v) { return 0 === strpos($v, './'); })
                                ->then(function($v) { return substr($v, 2); })
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;
    }

    public function analyze(Project $project, File $file)
    {
        $rulesets = $project->getFileConfig($file, 'php_md.rulesets');

        $configFiles = array();
        $resolvedRulesets = array();
        foreach ($rulesets as $ruleset) {
            if ($project->hasFile($ruleset)) {
                $cfgFile = $this->fs->createTempFile($project->getFile($ruleset)->getContent());
                $configFiles[] = $cfgFile;
                $resolvedRulesets[] = $cfgFile->getName();

                continue;
            }

            $resolvedRulesets[] = $ruleset;
        }

        $inputFile = $this->fs->createTempFile($file->getContent());

        $proc = new Process('phpmd '.escapeshellarg($inputFile->getName()).' xml '.escapeshellarg(implode(",", $resolvedRulesets)));
        $executedProc = $this->executor->execute($proc);
        $exitCode = $executedProc->getExitCode();

        if (0 !== $exitCode && 2 !== $exitCode) {
            throw new ProcessFailedException($executedProc);
        }

        $inputFile->delete();
        foreach ($configFiles as $file) {
            $file->delete();
        }

        $output = $executedProc->getOutput();
        $output = str_replace($inputFile->getName(), $file->getPath(), $output);
        $doc = XmlUtils::safeParse($output);

        // <error filename="syntax_error.php" msg="Unexpected end of token stream in file: syntax_error.php." />
        foreach ($doc->xpath('//error') as $error) {
            assert($error instanceof \SimpleXMLElement);

            $attrs = $error->attributes();
            $file->addComment(1, new Comment('php_md.error', (string) $attrs->msg));
        }

        // <violation beginline="4" endline="30" rule="CyclomaticComplexity" ruleset="Code Size Rules"
        //            package="+global" externalInfoUrl="http://phpmd.org/rules/codesize.html#cyclomaticcomplexity"
        //            class="Foo" method="example" priority="3"
        // >The method example() has a Cyclomatic Complexity of 11. The configured cyclomatic complexity threshold is 10.</violation>
        foreach ($doc->xpath('//violation') as $violation) {
            assert($violation instanceof \SimpleXMLElement);

            $attrs = $violation->attributes();
            $rule = preg_replace_callback('#[A-Z]#', function($v) { return '_'.strtolower($v[0]); }, lcfirst((string) $attrs->rule));
            $file->addComment((integer) $attrs->beginline, new Comment('php_md.'.$rule, trim((string) $violation)));
        }
    }
}