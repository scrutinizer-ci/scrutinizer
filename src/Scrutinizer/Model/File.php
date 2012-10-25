<?php

namespace Scrutinizer\Model;

class File
{
    private $path;
    private $content;
    private $comments = array();

    public function __construct($path, $content)
    {
        $this->path = $path;
        $this->content = $content;
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