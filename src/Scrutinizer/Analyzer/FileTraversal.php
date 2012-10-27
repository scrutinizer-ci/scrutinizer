<?php

namespace Scrutinizer\Analyzer;

use Scrutinizer\Model\File;
use Scrutinizer\Model\Project;

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

    public function setExtensions(array $extensions)
    {
        $this->extensions = $extensions;

        return $this;
    }

    public function traverse()
    {
        $name = $this->analyzer->getName();

        if ( ! $this->project->getGlobalConfig($name.'.enabled')) {
            return;
        }

        foreach ($this->project->getFiles() as $file) {
            assert($file instanceof File);

            if ($this->extensions && ! in_array($file->getExtension(), $this->extensions, true)) {
                continue;
            }

            if ( ! $this->project->getPathConfig($file, $name.'.enabled', true)) {
                continue;
            }

            $this->analyzer->{$this->method}($this->project, $file);
        }
    }
}