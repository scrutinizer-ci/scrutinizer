<?php

namespace Scrutinizer\Analyzer\Custom\Parser;

use Scrutinizer\Analyzer\Custom\ParserInterface;
use Scrutinizer\Model\Project;

class ScrutinizerJsonParser implements ParserInterface
{
    public function getFormat()
    {
        return 'scrutinizer_json';
    }

    public function parse(Project $project, $content)
    {

    }
}