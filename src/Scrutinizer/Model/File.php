<?php

namespace Scrutinizer\Model;

class File
{
    private $path;
    private $content;
    private $comments = array();
    
    private $fixedFile;

    public function __construct($path, $content)
    {
        $this->path = $path;
        $this->content = $content;
    }
    
    /**
     * @return Option<FixedFile>
     */
    public function getFixedFile()
    {
        return \PhpOption\Option::fromValue($this->fixedFile, null);
    }
    
    /**
     * Gets or creates the fixed file.
     * 
     * @return FixedFile
     */
    public function getOrCreateFixedFile()
    {
        if (null === $this->fixedFile) {
            return $this->fixedFile = new FixedFile($this->content);
        }
        
        return $this->fixedFile;
    }

    public function getPath()
    {
        return $this->path;
    }

    public function getContent()
    {
        return $this->content;
    }

    public function getExtension()
    {
        if (false !== $pos = strrpos($this->path, '.')) {
            return substr($this->path, $pos + 1);
        }

        return null;
    }

    public function addComment($line, Comment $comment)
    {
        $this->comments[$line][] = $comment;
    }

    public function hasComments()
    {
        return !! $this->comments;
    }

    public function getComments()
    {
        return $this->comments;
    }
}