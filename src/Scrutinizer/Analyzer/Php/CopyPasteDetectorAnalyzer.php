<?php

namespace Scrutinizer\Analyzer\Php;

use Scrutinizer\Analyzer\AbstractFileAnalyzer;
use Scrutinizer\Analyzer\Parser\CheckstyleParser;
use Scrutinizer\Config\ConfigBuilder;
use Scrutinizer\Model\Comment;
use Scrutinizer\Model\File;
use Scrutinizer\Model\Project;
use Scrutinizer\Util\XmlUtils;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

/**
 * Runs the PHP Copy Past Detector.
 *
 * @display-name PHPCPD
 * @doc-path tools/php/copy-paste-detector/
 */
class CopyPasteDetectorAnalyzer extends AbstractFileAnalyzer
{
    public function getInfo()
    {
        return 'Runs PHPCPD';
    }

    public function getName()
    {
        return 'php_cpd';
    }

    public function getDefaultExtensions()
    {
        return array('php');
    }

    public function buildConfigInternal(ConfigBuilder $builder)
    {
        $builder
            ->globalConfig()
                ->scalarNode('command')
                    ->defaultValue('phpcpd')
                ->end()
            ->end()
            ->perFileConfig()
                ->addDefaultsIfNotSet()
                ->children()
                    ->scalarNode('min_lines')
                        ->info('Minimum number of identical lines (default: 5)')
                        ->defaultValue(5)
                    ->end()
                    ->scalarNode('min_tokens')
                        ->info('Minimum number of identical tokens (default: 70)')
                        ->defaultValue(70)
                    ->end()
                    ->scalarNode('exclude')->end()
                ->end()
            ->end()
        ;
    }

    public function analyze(Project $project, File $file)
    {
        $command = $project->getGlobalConfig('command');
        $outputFile = tempnam(sys_get_temp_dir(), 'php-cpd.xml');
        $config = $project->getFileConfig($file);

        $cmd = $command.' --log-pmd '.$outputFile;

        if ( ! empty($config['exclude'])) {
            $cmd .= ' --exclude '.$config['exclude'];
        }

        $proc = new Process($cmd.' '.$project->getDir(), $project->getDir());
        $proc->run();

        $result = file_get_contents($outputFile);

        //unlink($outputFile);

        if ($proc->getExitCode() > 1) {
            throw new ProcessFailedException($proc);
        }

        $doc = XmlUtils::safeParse($result);
        foreach ($doc->xpath('//duplication') as $duplicationElem) {
            $duplication = array();

            foreach ($duplicationElem->xpath('file') as $fileElem) {
                /** @var $errorElem \SimpleXMLElement */
                $duplication[] = array(
                    'line' => (int) $fileElem->attributes()->line,
                    'path' => $fileElem->attributes()->path
                );
            }

            $fileA = reset($duplication);
            $fileB = end($duplication);

            $file->addComment(1, new Comment(
                $this->getName(),
                'php_cpd.'.uniqid(),
                "{lines} lines and {tokens} tokens duplicated between {pathA} (line {lineA}) and {pathB} ({lineB})",
                array(
                    'pathA' => $fileA['path'],
                    'lineA' => $fileA['line'],
                    'pathB' => $fileB['path'],
                    'lineB' => $fileB['line'],
                    'lines'  => $duplicationElem->attributes()->lines,
                    'tokens' => $duplicationElem->attributes()->tokens,
                ))
            );
        }
    }
}
