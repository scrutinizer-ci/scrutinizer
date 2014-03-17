<?php

namespace Scrutinizer\Analyzer\Ruby;

use PhpOption\Some;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use Scrutinizer\Analyzer\AnalyzerInterface;
use Scrutinizer\Config\ConfigBuilder;
use Scrutinizer\Model\Comment;
use Scrutinizer\Model\File;
use Scrutinizer\Model\Project;
use Scrutinizer\Process\Process;
use Scrutinizer\Util\PathUtils;
use Symfony\Component\Process\Exception\ProcessFailedException;

/**
 * Runs rubocop on your code.
 *
 * @doc-path tools/ruby/rubocop/
 * @display-name Rubocop
 */
class RubocopAnalyzer implements AnalyzerInterface, LoggerAwareInterface
{
    use LoggerAwareTrait;

    public function getName()
    {
        return 'rubocop';
    }

    public function buildConfig(ConfigBuilder $builder)
    {
        $builder
            ->info('Runs rubocop on your code.')
            ->globalConfig()
                ->scalarNode('command')
                    ->attribute('show_in_editor', false)
                ->end()
                ->booleanNode('lint_only')->defaultFalse()->end()
                ->booleanNode('auto_correct')->defaultTrue()->end()
            ->end()
        ;
    }

    public function scrutinize(Project $project)
    {
        $analysisDir = $this->prepareProjectForAnalysis($project);
        $outputFile = tempnam(sys_get_temp_dir(), 'rubocop-output');

        $cmd = $this->buildCommand($project, $analysisDir, $outputFile);

        $proc = new Process($cmd);
        $proc->setTimeout(1200);
        $proc->setIdleTimeout(120);
        $proc->setPty(true);
        $proc->setWorkingDirectory($analysisDir);

        $exitCode = $proc->run(function($_, $data) {
            $this->logger->info($data);
        });

        switch ($exitCode) {
            case 0:
                $this->cleanUpAfterAnalysis($analysisDir, $outputFile);
                break;

            case 1:
                $this->processOutput($project, $analysisDir, file_get_contents($outputFile));
                $this->cleanUpAfterAnalysis($analysisDir, $outputFile);
                break;

            default:
                $this->cleanUpAfterAnalysis($analysisDir, $outputFile);

                throw new ProcessFailedException($proc);
        }
    }

    private function cleanUpAfterAnalysis($analysisDir, $outputFile)
    {
        unlink($outputFile);

        $proc = new Process('rm -rf '.$analysisDir);
        $proc->run();
    }

    private function prepareProjectForAnalysis(Project $project)
    {
        $analysisDir = tempnam(sys_get_temp_dir(), 'rubocop-project');
        unlink($analysisDir);

        $proc = new Process('cp -R '.$project->getDir().' '.$analysisDir);
        $proc->mustRun();

        return $analysisDir;
    }

    private function buildCommand(Project $project, $analysisDir, $outputFile)
    {
        $cmd = $project->getGlobalConfig('command', new Some(__DIR__.'/../../../../vendor/bin/rubocop'));

        if ($project->getGlobalConfig('lint_only')) {
            $cmd .= ' --lint';
        }
        if ($project->getGlobalConfig('auto_correct')) {
            $cmd .= ' --auto-correct';
        }

        $cmd .= ' --require '.__DIR__.'/rubocop_progress_formatter.rb --format ScrutinizerFormatter';
        $cmd .= ' --format json --out '.escapeshellarg($outputFile);
        $cmd .= ' '.$analysisDir;

        return $cmd;
    }

    private function processOutput(Project $project, $analysisDir, $rawOutput)
    {
        $output = @json_decode($rawOutput, true);
        if ( ! is_array($output)) {
            throw new \RuntimeException('Could not parse JSON output: '.json_last_error());
        }

        $filter = $project->getGlobalConfig('filter');
        foreach ($output['files'] as $fileDetails) {
            if (PathUtils::isFiltered($fileDetails['path'], $filter)) {
                continue;
            }

            $project->getFile($fileDetails['path'])
                ->forAll(function(File $file) use ($fileDetails, $analysisDir) {
                    $hasCorrections = false;
                    foreach ($fileDetails['offenses'] as $offense) {
                        $hasCorrections = $hasCorrections || $offense['corrected'];

                        $file->addComment($offense['location']['line'], new Comment(
                            $this->getName(),
                            $this->getName().'.'.$offense['cop_name'],
                            $offense['message']
                        ));
                    }

                    if ($hasCorrections) {
                        $file->getOrCreateFixedFile()->setContent(file_get_contents($analysisDir.'/'.$file->getPath()));
                    }
                })
            ;
        }
    }
}