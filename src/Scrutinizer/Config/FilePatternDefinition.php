<?php

namespace Scrutinizer\Config;

use Symfony\Component\Config\Definition\Builder\NodeParentInterface;
use Symfony\Component\Config\Definition\Builder\ScalarNodeDefinition;

class FilePatternDefinition extends ScalarNodeDefinition
{
    public function __construct($name, NodeParentInterface $parent = null)
    {
        parent::__construct($name, $parent);

        $this->validate()->always(function($v) {
            if (substr($v, -1) === '/') {
                return $v.'*';
            }

            return $v;
        });
    }
}