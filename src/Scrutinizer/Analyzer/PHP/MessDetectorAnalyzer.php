<?php

namespace Scrutinizer\Analyzer\PHP;

use Scrutinizer\Model\Comment;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;
use Scrutinizer\Model\File;
use Scrutinizer\Config\ConfigBuilder;
use Scrutinizer\Analyzer\FileTraversal;
use Scrutinizer\Model\Project;
use Scrutinizer\Analyzer\AnalyzerInterface;

class MessDetectorAnalyzer implements AnalyzerInterface
{
    public function scrutinize(Project $project)
    {
        FileTraversal::create($project, $this, 'analyze')
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
                $cfgFile = tempnam(sys_get_temp_dir(), 'cfgFile');
                file_put_contents($cfgFile, $project->getFile($ruleset)->getContent());
                $resolvedRulesets[] = $configFiles[] = $cfgFile;

                continue;
            }

            $resolvedRulesets[] = $ruleset;
        }

        $inputFile = tempnam(sys_get_temp_dir(), 'inputFile');
        file_put_contents($inputFile, $file->getContent());

        $proc = new Process('phpmd '.escapeshellarg($inputFile).' xml '.escapeshellarg(implode(",", $resolvedRulesets)));
        $exitCode = $proc->run();

        if (0 !== $exitCode && 2 !== $exitCode) {
            throw new ProcessFailedException($proc);
        }

        unlink($inputFile);
        foreach ($configFiles as $file) {
            unlink($file);
        }

        $output = $proc->getOutput();
        $output = str_replace($inputFile, $file->getPath(), $output);

        $previous = libxml_disable_entity_loader(true);
        $doc = simplexml_load_string($output);
        libxml_disable_entity_loader($previous);

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