<?php

namespace Scrutinizer\Analyzer;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Scrutinizer\Cli\OutputLogger;
use Scrutinizer\Model\File;
use Scrutinizer\Model\Project;
use Symfony\Component\Console\Helper\ProgressHelper;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;
use Scrutinizer\Util\PathUtils;

/**
 * Convenience class for file traversals.
 *
 * This performs some additional checks in addition to simply traversing over
 * an array of files.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class FileTraversal
{
    private $project;
    private $analyzer;
    private $method;

    private $logger;
    private $extensions = array();

    public static function create(Project $project, AnalyzerInterface $analyzer, $method)
    {
        return new self($project, $analyzer, $method);
    }

    public function __construct(Project $project, AnalyzerInterface $analyzer, $method)
    {
        $this->project = $project;
        $this->analyzer = $analyzer;
        $this->method = $method;
        $this->logger = new NullLogger();
    }

    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;

        return $this;
    }

    public function setExtensions(array $extensions)
    {
        $this->extensions = $extensions;

        return $this;
    }

    public function traverse()
    {
        if ( ! $this->project->getGlobalConfig('enabled')) {
            return;
        }

        $files = $this->locateFiles();
        $progressOutput = $this->getProgressOutput();

        $i = 0;
        $total = count($files);
        foreach ($files as $finderFile) {
            /** @var $finderFile SplFileInfo */

            $this->project->getFile($finderFile->getRelativePathname())->forAll(function(File $file) {
                if (null !== $this->logger) {
                    $this->logger->debug(sprintf('Analyzing file "%s".'."\n", $file->getPath()), array('project' => $this->project, 'file' => $file, 'analyzer' => $this->analyzer));
                }

                try {
                    $this->analyzer->{$this->method}($this->project, $file);
                } catch (\Exception $ex) {
                    throw new \RuntimeException(
                        sprintf('An exception occurred while analyzing "%s": %s', $file->getPath(), $ex->getMessage()),
                        0,
                        $ex
                    );
                }
            });

            $this->advance($i, $total, $progressOutput);
        }

        $progressOutput->writeln("\n");
    }

    private function advance(&$i, $total, OutputInterface $output)
    {
        if ($i % 10 === 0) {
            $output->write('.');
        }

        $i += 1;

        if ($i % 800 === 0) {
            $output->writeln(" ".str_pad($i, strlen($total), ' ', STR_PAD_LEFT).'/'.$total);
        }
    }

    private function getProgressOutput()
    {
        return $this->logger instanceof OutputLogger && ! $this->logger->isVerbose() ? $this->logger->getOutput() : new NullOutput();
    }

    private function locateFiles()
    {
        $finder = (new ProjectIteratorFactory())->createFileIterator($this->project, $this->extensions);
        $files = iterator_to_array($finder);

        return $files;
    }
}
