<?php

namespace Scrutinizer\Model;

class Location
{
    private $filename;
    private $startLine;
    private $endLine;

    public function __construct($filename, $startLine = null, $endLine = null)
    {
        $this->filename = $filename;
        $this->startLine = $startLine;
        $this->endLine = $endLine;
    }

    public function getFilename()
    {
        return $this->filename;
    }

    public function getStartLine()
    {
        return $this->startLine;
    }

    public function getEndLine()
    {
        return $this->endLine;
    }
}