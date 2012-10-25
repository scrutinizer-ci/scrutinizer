<?php

namespace Scrutinizer\Config;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;

class ConfigBuilder extends TreeBuilder
{
    private $root;

    public static function create($name)
    {
        $builder = new self();

        return $builder->root($name);
    }

    public function root($name)
    {
        $this->root = parent::root($name, 'array', new NodeBuilder());

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

    public function getRoot()
    {
        return $this->root;
    }
}