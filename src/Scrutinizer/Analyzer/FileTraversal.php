<?php

namespace Scrutinizer\Analyzer;

use Psr\Log\LoggerInterface;
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
        $progressOutput->writeln('');
        $progress = $this->createProgress($files, $progressOutput);
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

            $progress->advance();
        }
        $progressOutput->writeln("\n");
    }

    private function getProgressOutput()
    {
        return $this->logger instanceof OutputLogger && ! $this->logger->isVerbose() ? $this->logger->getOutput() : new NullOutput();
    }

    private function locateFiles()
    {
        $finder = Finder::create()
            ->in($this->project->getDir())
            ->files()
            ->filter(function (SplFileInfo $file) {
                if ( ! $this->project->isAnalyzed($file->getRelativePathname())) {
                    return false;
                }

                if ( PathUtils::isFiltered($file->getRelativePathname(), $this->project->getGlobalConfig('filter'))) {
                    return false;
                }

                if ($this->extensions && ! in_array($file->getExtension(), $this->extensions, true)) {
                    return false;
                }

                if ( ! $this->project->getPathConfig($file->getRelativePath(), 'enabled', true)) {
                    return false;
                }

                return true;
            })
        ;
        $files = iterator_to_array($finder);

        return $files;
    }

    private function createProgress(array $files, OutputInterface $output)
    {
        $progress = new ProgressHelper();
        $progress->setFormat('    Files %current%/%max% [%bar%] %percent%%');
        $progress->setBarCharacter('.');
        $progress->setEmptyBarCharacter(' ');
        $progress->setBarWidth(60);
        $progress->start($output, count($files));

        return $progress;
    }
}
