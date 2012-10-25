<?php

namespace Scrutinizer\Model;

class Project
{
    private $files;
    private $config;

    public function __construct(array $files, array $config)
    {
        $this->files = $files;
        $this->config = $config;
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