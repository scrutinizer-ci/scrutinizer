<?php

namespace Scrutinizer\Analyzer;

use Scrutinizer\Model\File;
use Scrutinizer\Model\Project;

class FileTraversal
{
    private $project;
    private $callback;

    private $extensions = array();

    public static function create(Project $project, $callback)
    {
        return new self($project, $callback);
    }

    public function __construct(Project $project, $callback)
    {
        if ( ! is_callable($callback)) {
            throw new \InvalidArgumentException('$callback must be a valid callable.');
        }

        $this->project = $project;
        $this->callback = $callback;
    }

    public function setExtensions(array $extensions)
    {
        $this->extensions = $extensions;

        return $this;
    }

    public function traverse()
    {
        foreach ($this->project->getFiles() as $file) {
            assert($file instanceof File);

            if ($this->extensions && ! in_array($file->getExtension(), $this->extensions, true)) {
                continue;
            }

            $config = $this->project->getConfigForPath($file->getPath());
            if ( ! $config['enabled']) {
                continue;
            }

            call_user_func($this->callback, $file, $config);
        }
    }
}