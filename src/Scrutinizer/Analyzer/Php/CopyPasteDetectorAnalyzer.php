<?php

namespace Scrutinizer\Analyzer\Php;

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Scrutinizer\Analyzer\AbstractFileAnalyzer;
use Scrutinizer\Analyzer\AnalyzerInterface;
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
 * @display-name PHP Copy/Paste Detector
 * @doc-path tools/php/copy-paste-detector/
 */
class CopyPasteDetectorAnalyzer implements AnalyzerInterface, LoggerAwareInterface
{
    use LoggerAwareTrait;

    public function getName()
    {
        return 'php_cpd';
    }

    public function buildConfig(ConfigBuilder $builder)
    {
        $builder
            ->info('Runs PHP Copy/Paste Detector')
            ->disableDefaultFilter()
            ->globalConfig()
                ->scalarNode('command')
                    ->defaultValue('phpcpd')
                ->end()
                ->scalarNode('min_lines')
                    ->info('Minimum number of identical lines (default: 5)')
                    ->defaultValue(5)
                ->end()
                ->scalarNode('min_tokens')
                    ->info('Minimum number of identical tokens (default: 70)')
                    ->defaultValue(70)
                ->end()
                ->arrayNode('excluded_dirs')
                    ->info('A list of excluded directories.')
                    ->prototype('scalar')->end()
                ->end()
                ->arrayNode('names')
                    ->info('A list of names that should be scanned (default: *.php)')
                    ->requiresAtLeastOneElement()
                    ->defaultValue(array('*.php'))
                    ->prototype('scalar')->end()
                ->end()
            ->end()
        ;
    }

    private function buildCommand(Project $project, $outputFile)
    {
        $command = sprintf(
            '%s --progress --log-pmd %s --min-lines %d --min-tokens %d --names %s',
            $project->getGlobalConfig('command'),
            escapeshellarg($outputFile),
            $project->getGlobalConfig('min_lines'),
            $project->getGlobalConfig('min_tokens'),
            escapeshellarg(implode(',', $project->getGlobalConfig('names')))
        );

        $excludedDirs = $project->getGlobalConfig('excluded_dirs');
        if ( ! empty($excludedDirs)) {
            foreach ($excludedDirs as $dir) {
                $command .= sprintf(' --exclude %s' , escapeshellarg($dir));
            }
        }

        // Scan the current directory.
        $command .= ' .';

        return $command;
    }

    public function scrutinize(Project $project)
    {
        $outputFile = tempnam(sys_get_temp_dir(), 'phpcpd');

        $command = $this->buildCommand($project, $outputFile);

        $this->logger->info('$ '.$command."\n");
        $proc = new Process($command, $project->getDir());
        $proc->setTimeout(900);
        $proc->setIdleTimeout(180);
        $proc->setPty(true);
        $proc->run(function($_, $data) {
            $this->logger->info($data);
        });

        $result = file_get_contents($outputFile);
        unlink($outputFile);

        if ($proc->getExitCode() > 1) {
            throw new ProcessFailedException($proc);
        }

        $doc = XmlUtils::safeParse($result);
        foreach ($doc->xpath('//duplication') as $duplicationElem) {
            $duplication = array();

            foreach ($duplicationElem->xpath('file') as $fileElem) {
                $path = substr($fileElem->attributes()->path, strlen($project->getDir()) + 1);

                /** @var $errorElem \SimpleXMLElement */
                $duplication[] = array(
                    'line' => (int) $fileElem->attributes()->line,
                    'path' => $path,
                    'file' => $project->getFile($path),
                );
            }

            // PHP CPD seems to only support two elements at the moment.
            $entryA = reset($duplication);
            $entryB = end($duplication);

            if ($project->isAnalyzed($entryA['path'])) {
                $this->addDuplicationComment($duplicationElem, $entryA, $entryB);
            }

            if ($project->isAnalyzed($entryB['path'])) {
                $this->addDuplicationComment($duplicationElem, $entryB, $entryA);
            }
        }
    }

    private function addDuplicationComment(\SimpleXMLElement $duplicationElem, array $fileEntry, array $otherEntry)
    {
        /** @var $file File */
        $file = $fileEntry['file']->get();

        $file->addComment($fileEntry['line'], new Comment(
            $this->getName(),
            'php_cpd.duplication',
            'This and the next {duplicateLines} lines are the same as lines {otherStartingLine} to {otherEndingLine} in {otherPath}.',
            array(
                'duplicateLines' => (int) $duplicationElem->attributes()->lines,
                'otherPath' => $otherEntry['path'],
                'otherStartingLine' => $otherEntry['line'],
                'otherEndingLine' => $otherEntry['line'] + (int) $duplicationElem->attributes()->lines,
            )
        ));
    }
}
