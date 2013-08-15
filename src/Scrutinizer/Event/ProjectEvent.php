<?php

namespace Scrutinizer\Event;

use Scrutinizer\Model\Project;
use Symfony\Component\EventDispatcher\Event;

class ProjectEvent extends Event
{
    private $project;

    public function __construct(Project $project)
    {
        $this->project = $project;
    }

    /**
     * @return Project
     */
    public function getProject()
    {
        return $this->project;
    }
}