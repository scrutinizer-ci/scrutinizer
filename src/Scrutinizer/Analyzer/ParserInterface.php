<?php

namespace Scrutinizer\Analyzer;

use Scrutinizer\Model\Project;

interface ParserInterface
{
    /**
     * Returns the format of this parser.
     *
     * @return string
     */
    public function getFormat();

    /**
     * @param string $content
     *
     * @return void
     */
    public function parse($content);
}