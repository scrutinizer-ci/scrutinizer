<?php

namespace Scrutinizer\Util;

class File
{
    private $fs;
    private $name;
    private $content;
    
    public function __construct(Filesystem $fs, $name, $content)
    {
        $this->fs = $fs;
        $this->name = $name;
        $this->content = $content;
    }
    
    public function rename($newName)
    {
        $this->fs->rename($this->name, $newName);
        $this->name = $newName;
    }
    
    public function delete()
    {
        $this->fs->delete($this);
    }
    
    public function getName()
    {
        return $this->name;
    }
    
    public function getContent()
    {
        return $this->content;
    }
    
    public function setContent($newContent)
    {
        $this->content = $newContent;
        $this->fs->write($this);
    }
}