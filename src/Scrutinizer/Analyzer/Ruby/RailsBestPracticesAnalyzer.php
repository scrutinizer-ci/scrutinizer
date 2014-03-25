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
use Scrutinizer\Util\YamlUtils;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Yaml\Yaml;

/**
 * Runs rails_best_practices analyzer on your code.
 *
 * @doc-path tools/ruby/rails-best-practices/
 * @display-name Rails Best Practices
 */
class RailsBestPracticesAnalyzer implements AnalyzerInterface, LoggerAwareInterface
{
    use LoggerAwareTrait;

    public function getName()
    {
        return 'rails_best_practices';
    }

    public function buildConfig(ConfigBuilder $builder)
    {
        $builder
            ->info('Runs rails best practices analysis on your code.')
            ->globalConfig()
                ->scalarNode('command')
                    ->attribute('show_in_editor', false)
                ->end()
                ->booleanNode('include_vendors')->defaultFalse()->end()
                ->booleanNode('include_specs')->defaultFalse()->end()
                ->booleanNode('include_tests')->defaultFalse()->end()
                ->booleanNode('include_features')->defaultFalse()->end()
            ->end()
        ;
    }

    public function scrutinize(Project $project)
    {
        $outputFile = tempnam(sys_get_temp_dir(), 'rails-best-practices-output');
        $cmd = $this->buildCommand($project, $outputFile);

        $proc = new Process($cmd);
        $proc->setTimeout(1200);
        $proc->setIdleTimeout(120);
        $proc->setPty(true);
        $proc->setWorkingDirectory($project->getDir());

        $exitCode = $proc->run(function($_, $data) {
            $this->logger->info($data);
        });

        $output = file_get_contents($outputFile);
        unlink($outputFile);

        switch ($exitCode) {
            case 0:
                break;

            case 2:
                $this->processOutput($project, $output);
                break;

            default:
                throw new ProcessFailedException($proc);
        }
    }

    private function buildCommand(Project $project, $outputFile)
    {
        $cmd = $project->getGlobalConfig('command', new Some(__DIR__.'/../../../../vendor/bin/rails_best_practices'));

        if ($project->getGlobalConfig('include_vendors')) {
            $cmd .= ' --vendor';
        }
        if ($project->getGlobalConfig('include_specs')) {
            $cmd .= ' --spec';
        }
        if ($project->getGlobalConfig('include_tests')) {
            $cmd .= ' --test';
        }
        if ($project->getGlobalConfig('include_features')) {
            $cmd .= ' --features';
        }

        $cmd .= ' --format yaml --output-file '.escapeshellarg($outputFile);
        $cmd .= ' '.$project->getDir();

        return $cmd;
    }

    private function processOutput(Project $project, $rawOutput)
    {
        $offenses = $this->parseOutput($rawOutput);

        $filter = $project->getGlobalConfig('filter');
        foreach ($offenses as $path => $pathOffenses) {
            $relativePath = substr($path, strlen($project->getDir()) + 1);

            if (PathUtils::isFiltered($relativePath, $filter)) {
                continue;
            }

            $project->getFile($relativePath)
                ->forAll(function(File $file) use ($pathOffenses) {
                    foreach ($pathOffenses as $offense) {
                        $file->addComment($offense['line'], new Comment(
                            $this->getName(),
                            $this->getName().'.'.$offense['id'],
                            $offense['message']
                        ));
                    }
                })
            ;
        }
    }

    private function parseOutput($rawOutput)
    {
        $offenses = array();

        $lines = explode("\n", $rawOutput);
        for ($i=1,$c=count($lines); $i<$c; $i++) {
            if (substr($lines[$i], 0, 2) !== '- ') {
                break;
            }

            $offense = array();
            while (isset($lines[$i+1]) && substr($lines[$i+1], 0, 2) === '  ') {
                $key = substr($lines[$i+1], 2, strpos($lines[$i+1], ':') - 2);
                $value = Yaml::parse(substr($lines[$i+1], strpos($lines[$i+1], ':') + 1));

                $this->updateOffense($offense, $key, $value);

                $i++;
            }

            $offenses[$offense['path']][] = $offense;
        }

        return $offenses;
    }

    private function updateOffense(array &$offense, $key, $value)
    {
        switch ($key) {
            case 'filename':
                $offense['path'] = $value;
                break;

            case 'line_number':
                $offense['line'] = (integer) $value;
                break;

            case 'type':
                $offense['id'] = $value;
                break;

            case 'message':
                $offense['message'] = $value;
                break;
        }
    }
}