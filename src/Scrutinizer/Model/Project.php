<?php

namespace Scrutinizer\Model;

class Project
{
    private $files;

    public function __construct(array $files = array())
    {
        $this->files = $files;
    }

    public function getConfigForPath($path)
    {
        return array();
    }

    public function getFiles()
    {
        return $this->files;
    }
}