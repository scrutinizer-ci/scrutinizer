<?php

namespace Scrutinizer\Analyzer;

use Psr\Log\LoggerInterface;
use Scrutinizer\Model\Project;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

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

        $finder = Finder::create()
            ->in($this->project->getDir())
            ->files()
            ->filter(function (SplFileInfo $file) {
                if ( ! $this->project->isAnalyzed($file->getRelativePathname())) {
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

        foreach ($finder as $finderFile) {
            /** @var $finderFile SplFileInfo */

            $file = $this->project->getFile($finderFile->getRelativePathname())->get();

            if (null !== $this->logger) {
                $this->logger->debug(sprintf('Analyzing file "%s".', $file->getPath()), array('project' => $this->project, 'file' => $file, 'analyzer' => $this->analyzer));
            }
            $this->analyzer->{$this->method}($this->project, $file);
        }
    }
}
