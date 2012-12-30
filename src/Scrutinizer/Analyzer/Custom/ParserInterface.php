<?php

namespace Scrutinizer\Analyzer\Custom;

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
     * @param \Scrutinizer\Model\Project $project
     * @param string $content
     *
     * @return void
     */
    public function parse(Project $project, $content);
}