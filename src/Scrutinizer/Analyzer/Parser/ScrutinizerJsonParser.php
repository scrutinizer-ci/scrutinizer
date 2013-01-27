<?php

namespace Scrutinizer\Analyzer\Parser;

use Scrutinizer\Analyzer\ParserInterface;
use Scrutinizer\Model\Project;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\Processor;

class ScrutinizerJsonParser implements ParserInterface
{
    public function getFormat()
    {
        return 'scrutinizer_json';
    }

    public function parse($content)
    {
        $result = json_decode($content, true);
        if (false === $result) {
            throw new \RuntimeException('The JSON content could not be parsed: '.json_last_error());
        }

        return $result;
    }
}