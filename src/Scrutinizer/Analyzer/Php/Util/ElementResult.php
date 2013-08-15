<?php

namespace Scrutinizer\Analyzer\Php\Util;

class ElementResult
{
    /** @var \PHPParser_NodeAbstract */
    public $classes;

    /** @var \PHPParser_NodeAbstract */
    public $functions;

    public function __construct(array $classes, array $functions)
    {
        $this->classes = $classes;
        $this->functions = $functions;
    }
}