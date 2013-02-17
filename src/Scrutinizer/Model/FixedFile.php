<?php

namespace Scrutinizer\Model;

/**
 * Fixed File.
 * 
 * Currently, we support changing the content only.
 * 
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class FixedFile
{
    private $content;

    public function __construct($content)
    {
        $this->content = $content;
    }

    public function setContent($content)
    {
        $this->content = $content;
    }

    public function getContent()
    {
        return $this->content;
    }
}