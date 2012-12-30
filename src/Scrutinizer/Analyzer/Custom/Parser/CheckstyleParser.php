<?php

namespace Scrutinizer\Analyzer\Custom\Parser;

use Scrutinizer\Analyzer\Custom\ParserInterface;

class CheckstyleParser implements ParserInterface
{
    public function getFormat()
    {
        return 'checkstyle';
    }
}