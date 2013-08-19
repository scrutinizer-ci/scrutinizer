<?php

namespace Scrutinizer\Config;

use Symfony\Component\Config\Definition\Builder\NodeBuilder as BaseNodeBuilder;

class NodeBuilder extends BaseNodeBuilder
{
    public function __construct()
    {
        parent::__construct();

        $this->setNodeClass('file_pattern', 'Scrutinizer\Config\FilePatternDefinition');
    }
}