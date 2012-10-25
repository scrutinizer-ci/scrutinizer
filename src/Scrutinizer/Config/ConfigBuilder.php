<?php

namespace Scrutinizer\Config;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;

class ConfigBuilder
{
    private $root;

    public function analyzer($name, $desc)
    {
        return $this->root = new AnalyzerBuilder($name, $desc);
    }

    public function getRoot()
    {
        return $this->root;
    }
}