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
 * @doc-path tools/ruby/flay/
 * @display-name flay
 */
class FlayAnalyzer implements AnalyzerInterface, LoggerAwareInterface
{
    use LoggerAwareTrait;

    public function getName()
    {
        return 'ruby_flay';
    }

    public function buildConfig(ConfigBuilder $builder)
    {
        $builder
            ->info('Runs flay\'s similarity analysis on your code.')
            ->globalConfig()
                ->scalarNode('command')
                    ->attribute('show_in_editor', false)
                ->end()
                ->scalarNode('mass')->defaultValue(16)->end()
            ->end()
        ;
    }

    public function scrutinize(Project $project)
    {
        $cmd = $this->buildCommand($project);

        $proc = new Process($cmd);
        $proc->setTimeout(1200);
        $proc->setIdleTimeout(120);
        $proc->setWorkingDirectory($project->getDir());

        $exitCode = $proc->run(function($_, $data) {
            $this->logger->info($data);
        });

        switch ($exitCode) {
            case 0:
                $this->processOutput($project, $proc->getOutput());
                break;

            default:
                throw new ProcessFailedException($proc);
        }
    }

    private function buildCommand(Project $project)
    {
        $cmd = $project->getGlobalConfig('command', new Some(__DIR__.'/../../../../vendor/bin/flay'));

        $cmd .= ' --diff --mass '.$project->getGlobalConfig('mass');
        $cmd .= ' '.$project->getDir();

        return $cmd;
    }

    private function processOutput(Project $project, $rawOutput)
    {
        $lexer = new FlayLexer($rawOutput);
        $lexer->moveNext();

        while ($lexer->hasMoreLines()) {
            while ( ! $lexer->isNextDuplicationStart()) {
                $lexer->moveNext();

                if ( ! $lexer->hasMoreLines()) {
                    break 2;
                }
            }

            $duplication = $this->parseDuplication($project, $lexer);
            if (count($duplication['locations']) > 1) {
                $this->applyDuplicationData($project, $duplication);
            }
        }
    }

    private function applyDuplicationData(Project $project, array $duplicationDetails)
    {
        foreach ($duplicationDetails['locations'] as $location) {
            $project->getFile($location['path'])
                ->forAll(function(File $file) use ($location, $duplicationDetails) {
                    $file->setLineAttribute($location['line'], 'duplication', $duplicationDetails);
                })
            ;
        }
    }

    private function parseDuplication(Project $project, FlayLexer $lexer)
    {
        $lexer->moveNext();
        $mass = $this->parseMass($lexer->line);

        list($files, $startLineContents) = $this->parseLocations($project, $lexer);

        $lexer->moveNext();

        while ($lexer->hasMoreLines() && ! $lexer->isNextDuplicationStart() && $lexer->nextLine !== '') {
            $lexer->moveNext();

            foreach ($startLineContents as $k => $content) {
                if (0 === strpos($content, substr($lexer->line, 3))) {
                    $files[$k]['line'] -= $files[$k]['length'];
                    unset($startLineContents[$k]);
                }
            }

            if ($lexer->line[0] === ' ') {
                foreach ($files as $k => &$data) {
                    $data['length'] += 1;
                }
            } else if (isset($files[$lexer->line[0]])) {
                $files[$lexer->line[0]]['length'] += 1;
            }
        }

        return array(
            'mass' => $mass,
            'locations' => array_values($files),
        );
    }

    private function parseLocations(Project $project, FlayLexer $lexer)
    {
        $files = array();
        $startLineContents = array();
        while ($lexer->nextLine !== '') {
            $lexer->moveNext();

            list($abbr, $path, $startLine) = $this->parseFilePath($project, $lexer->line);
            if ($project->getFile($path)->isEmpty() || PathUtils::isFiltered($path, $project->getGlobalConfig('filter'))) {
                continue;
            }

            $files[$abbr] = array(
                'path' => $path,
                'line' => $startLine,
                'length' => 0,
            );
            $startLineContents[$abbr] = $project->getFile($path)
                ->map(function(File $file) use ($startLine) {
                    return explode("\n", $file->getContent())[$startLine - 1];
                })
                ->get()
            ;
        }

        return array($files, $startLineContents);
    }

    private function parseFilePath(Project $project, $line)
    {
        if (preg_match('/^\s+([A-Z]+)\: ([^$]+)\:([0-9]+)$/', $line, $match)) {
            $relativePath = substr($match[2], strlen($project->getDir()) + 1);

            return array($match[1], $relativePath, $match[3]);
        }

        throw new \RuntimeException(sprintf('Could not parse file path from "%s".', $line));
    }

    private function parseMass($line)
    {
        if (preg_match('/mass(?:\*[0-9]+)?\s*=\s*([0-9]+)/', $line, $match)) {
            return (integer) $match[1];
        }

        throw new \RuntimeException(sprintf('Could not extract mass from "%s".', $line));
    }
}

class FlayLexer
{
    public $line;
    public $nextLine;

    private $lines;
    private $pointer = 0;

    public function __construct($output)
    {
        $this->lines = explode("\n", $output);
        $this->nextLine = $this->lines[0];
    }

    public function hasMoreLines()
    {
        return $this->nextLine !== null;
    }

    public function moveNext()
    {
        $this->pointer += 1;
        $this->line = $this->nextLine;
        $this->nextLine = isset($this->lines[$this->pointer]) ? $this->lines[$this->pointer] : null;
    }

    public function isNextDuplicationStart()
    {
        return $this->nextLine !== null && strlen($this->nextLine) > 2 && $this->nextLine[1] === ')';
    }
}