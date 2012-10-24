<?php

namespace Scrutinizer\Config;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\Builder\NodeBuilder;

class ConfigBuilder
{
    private $root;

    public static function create($name)
    {
        $builder = new self();

        return $builder->root($name);
    }

    public function root($name)
    {
        $builder = new NodeBuilder();

        $this->root = $builder->arrayNode($name);
        $this->root
            ->treatNullLike(array('enabled' => true))
            ->treatTrueLike(array('enabled' => true))
            ->treatFalseLike(array('enabled' => false))
            ->children()
                ->booleanNode('enabled')->defaultFalse()->end()
            ->end()
        ;

        return $this->root;
    }

    public function build()
    {
        return $this->root->getNode(true);
    }
}