<?php

namespace Scrutinizer\Analyzer\Php;

use Scrutinizer\Analyzer\AbstractFileAnalyzer;
use Scrutinizer\Util\XmlUtils;
use Scrutinizer\Model\Comment;
use Scrutinizer\Model\File;
use Scrutinizer\Config\ConfigBuilder;
use Scrutinizer\Analyzer\FileTraversal;
use Scrutinizer\Model\Project;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

/**
 * Integrates PHP Mess Detector.
 *
 * @doc-path tools/php/mess-detector/
 * @display-name PHP Mess Detector
 */
class MessDetectorAnalyzer extends AbstractFileAnalyzer
{
    public function getName()
    {
        return 'php_mess_detector';
    }

    protected function getInfo()
    {
        return 'Runs the PHP Mess Detector (http://phpmd.org).';
    }

    protected function getDefaultExtensions()
    {
        return array('php');
    }

    protected function buildConfigInternal(ConfigBuilder $builder)
    {
        $builder
            ->globalConfig()
                ->scalarNode('command')
                    ->defaultValue('phpmd')
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
                                ->ifTrue(function($v) {
                                    if ( ! is_string($v)) {
                                        return false;
                                    }

                                    return 0 === strpos($v, './');
                                })
                                ->then(function($v) {
                                    return substr($v, 2);
                                })
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;
    }

    public function analyze(Project $project, File $file)
    {
        $command = $project->getGlobalConfig('command');
        $rulesets = $project->getFileConfig($file, 'rulesets');

        $configFiles = array();
        $resolvedRulesets = array();
        foreach ($rulesets as $ruleset) {
            $ruleFile = $project->getFile($ruleset);
            if ($ruleFile->isDefined()) {
                $cfgFile = tempnam(sys_get_temp_dir(), 'phpmd_cfg');
                file_put_contents($cfgFile, $ruleFile->get()->getContent());

                $configFiles[] = $cfgFile;
                $resolvedRulesets[] = $cfgFile;

                continue;
            }

            $resolvedRulesets[] = $ruleset;
        }

        $inputFile = tempnam(sys_get_temp_dir(), 'phpmd_input');
        file_put_contents($inputFile, $file->getContent());

        $proc = new Process($command.' '.escapeshellarg($inputFile).' xml '.escapeshellarg(implode(",", $resolvedRulesets)));
        $proc->setTimeout(300);
        $exitCode = $proc->run();

        if (0 !== $exitCode && 2 !== $exitCode) {
            throw new ProcessFailedException($proc);
        }

        unlink($inputFile);
        array_map('unlink', $configFiles);

        $output = $proc->getOutput();
        $output = str_replace($inputFile, $file->getPath(), $output);
        $doc = XmlUtils::safeParse($output);

        // <error filename="syntax_error.php" msg="Unexpected end of token stream in file: syntax_error.php." />
        foreach ($doc->xpath('//error') as $error) {
            /** @var $error \SimpleXMLElement */

            $attrs = $error->attributes();
            $file->addComment(1, new Comment($this->getName(), 'php_md.error', (string) $attrs->msg));
        }

        // <violation beginline="4" endline="30" rule="CyclomaticComplexity" ruleset="Code Size Rules"
        //            package="+global" externalInfoUrl="http://phpmd.org/rules/codesize.html#cyclomaticcomplexity"
        //            class="Foo" method="example" priority="3"
        // >The method example() has a Cyclomatic Complexity of 11. The configured cyclomatic complexity threshold is 10.</violation>
        foreach ($doc->xpath('//violation') as $violation) {
            /** @var $violation \SimpleXMLElement */

            $attrs = $violation->attributes();
            $rule = preg_replace_callback('#[A-Z]#', function($v) { return '_'.strtolower($v[0]); }, lcfirst((string) $attrs->rule));
            $file->addComment((integer) $attrs->beginline, new Comment($this->getName(), 'php_md.'.$rule, trim((string) $violation)));
        }
    }
}
