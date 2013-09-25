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
use Scrutinizer\Util\PathUtils;
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
            ->globalConfig()
                ->scalarNode('command')
                    ->defaultValue('phpcpd')
                ->end()
                ->arrayNode('excluded_dirs')
                    ->info('A list of excluded directories.')
                    ->attribute('label', 'Excluded Directories')
                    ->attribute('help_block', 'One directory per line.')
                    ->prototype('scalar')
                        ->validate()->always(function($v) {
                            if (substr($v, -2) === '/*') {
                                return substr($v, 0, -2);
                            }

                            return $v;
                        })->end()
                    ->end()
                ->end()
                ->arrayNode('names')
                    ->attribute('show_in_editor', false)
                    ->info('A list of names that should be scanned (default: *.php)')
                    ->requiresAtLeastOneElement()
                    ->defaultValue(array('*.php'))
                    ->prototype('scalar')->end()
                ->end()
                ->scalarNode('min_lines')
                    ->info('Minimum number of identical lines (default: 5)')
                    ->attribute('label', 'Minimum of Lines')
                    ->attribute('help_inline', 'The minimum number of identical lines before assuming duplication.')
                    ->defaultValue(5)
                ->end()
                ->scalarNode('min_tokens')
                    ->info('Minimum number of identical tokens (default: 70)')
                    ->attribute('label', 'Minimum of Tokens')
                    ->attribute('help_inline', 'The minimum number of identical tokens before assuming duplication.')
                    ->defaultValue(70)
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
        $command .= ' '.$project->getDir();

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

        if (empty($result)) {
            if ($proc->getExitCode() !== 0) {
                throw new ProcessFailedException($proc);
            }

            return;
        }

        $doc = XmlUtils::safeParse($result);
        $duplications = $this->extractDuplications($project, $doc);
        $combinedDuplications = $this->combineDuplications($duplications);
        $filter = $project->getGlobalConfig('filter');

        foreach ($combinedDuplications as $duplication) {
            foreach ($duplication['locations'] as $location) {
                if (PathUtils::isFiltered($location['path'], $filter)) {
                    continue;
                }
                if ( ! $project->isAnalyzed($location['path'])) {
                    continue;
                }

                $project->getFile($location['path'])->forAll(function(File $file) use ($duplication, $location) {
                    $otherLocations = array();
                    foreach ($duplication['locations'] as $otherLocation) {
                        if ( ! $this->equalsLocation($location, $otherLocation)) {
                            $otherLocations[] = $otherLocation;
                        }
                    }

                    $file->setLineAttribute($location['line'], 'duplication', $duplication);

                    switch (count($otherLocations)) {
                        case 0:
                            throw new \LogicException('Should never be reached.');

                        case 1:
                            $file->addComment($location['line'], new Comment(
                                $this->getName(),
                                'php_cpd.duplication',
                                'This and the next {duplicateLines} lines are the same as lines {otherStartingLine} to {otherEndingLine} in {otherPath}.',
                                array(
                                    'duplicateLines' => $duplication['lines'],
                                    'otherPath' => $otherLocations[0]['path'],
                                    'otherStartingLine' => $otherLocations[0]['line'],
                                    'otherEndingLine' => $otherLocations[0]['line'] + $duplication['lines'],
                                )
                            ));
                            break;

                        default:
                            $locations = implode(", ", array_map(function(array $location) {
                                return $location['path'].' (line: '.$location['line'].')';
                            }, $otherLocations));

                            $file->addComment($location['line'], new Comment(
                                $this->getName(),
                                'php_cpd.multiple_duplications',
                                'This and the next {duplicateLines} lines are duplicated in {nbDuplications} other locations: {locations}',
                                array(
                                    'duplicateLines' => $duplication['lines'],
                                    'nbDuplications' => count($otherLocations),
                                    'locations' => $locations,
                                )
                            ));
                            break;
                    }
                });
            }
        }
    }

    private function equalsLocation(array $a, array $b)
    {
        return $a['line'] === $b['line'] && $a['path'] === $b['path'];
    }

    private function equalsDuplication(array $a, array $b)
    {
        if ($a['lines'] !== $b['lines']) {
            return false;
        }

        foreach ($a['locations'] as $aFile) {
            foreach ($b['locations'] as $bFile) {
                if ($this->equalsLocation($aFile, $bFile)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function combineDuplications(array $duplications)
    {
        $combinedDuplications = array();

        foreach ($duplications as $duplication) {
            foreach ($combinedDuplications as &$combinedDuplication) {
                if ( ! $this->equalsDuplication($duplication, $combinedDuplication)) {
                    continue;
                }

                foreach ($duplication['locations'] as $file) {
                    $found = false;
                    foreach ($combinedDuplication['locations'] as $combinedFile) {
                        if ($this->equalsLocation($combinedFile, $file)) {
                            $found = true;
                            break;
                        }
                    }

                    if ( ! $found) {
                        $combinedDuplication['locations'][] = $file;
                    }
                }

                continue 2;
            }

            $combinedDuplications[] = $duplication;
        }

        foreach ($combinedDuplications as &$duplication) {
            $duplication['locations'] = $this->sortFiles($duplication['locations']);
        }

        return $combinedDuplications;
    }

    private function extractDuplications(Project $project, \SimpleXMLElement $doc)
    {
        $duplications = array();
        foreach ($doc->xpath('//duplication') as $duplicationElem) {
            $files = $this->extractFiles($project->getDir(), $duplicationElem);

            $duplications[] = array(
                'lines' => (int) $duplicationElem->attributes()->lines,
                'locations' => $files,
            );
        }

        return $duplications;
    }

    private function sortFiles(array $files)
    {
        usort($files, function($a, $b) {
            return strcasecmp($a['path'], $b['path']);
        });

        return $files;
    }

    private function extractFiles($projectDir, \SimpleXMLElement $duplicationElem)
    {
        $files = array();
        foreach ($duplicationElem->xpath('file') as $fileElem) {
            $path = substr($fileElem->attributes()->path, strlen($projectDir) + 1);

            /** @var $errorElem \SimpleXMLElement */

            $files[] = array(
                'path' => $path,
                'line' => (int) $fileElem->attributes()->line,
            );
        }

        return $files;
    }
}
