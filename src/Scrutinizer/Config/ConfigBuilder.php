<?php

namespace Scrutinizer\Config;

use Symfony\Component\Config\Definition\PrototypedArrayNode;
use Symfony\Component\Config\Definition\BooleanNode;
use Symfony\Component\Config\Definition\ArrayNode;
use Symfony\Component\Config\Definition\Builder\NodeParentInterface;
use Symfony\Component\Config\Definition\Builder\NodeDefinition;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;

/**
 * Specialized builder to enforce a default config structure for analyzers.
 *
 * This adds some predictability to the config structure while also safing us
 * to type that much.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class ConfigBuilder extends ArrayNodeDefinition
{
    private $perFileConfigDef;

    public function __construct($name)
    {
        parent::__construct($name);

        $this->setBuilder(new NodeBuilder());
        $this
            ->addDefaultsIfNotSet()
            ->beforeNormalization()
                ->always(function($v) {
                    if (is_array($v) && ! array_key_exists('enabled', $v)) {
                        $v['enabled'] = true;
                    }

                    return $v;
                })
            ->end()
        ;
    }

    public function children()
    {
        throw new \LogicException('Please use globalConfig() instead.');
    }

    public function globalConfig()
    {
        return $this->getNodeBuilder();
    }

    public function perFileConfig($nodeType = 'array')
    {
        if (null === $this->perFileConfigDef) {
            $builder = new NodeBuilder();
            $this->perFileConfigDef = $builder->node('config', $nodeType);
        }

        return $this->perFileConfigDef;
    }

    protected function createNode()
    {
        if ( ! isset($this->attributes['info'])) {
            throw new \RuntimeException(sprintf('Each analyzer should have some short info. You can add by calling $configBuilder->info("my info").'));
        }

        // Overwrite special treatmeant of booleans, and null.
        $this->treatTrueLike(array('enabled' => true));
        $this->treatNullLike(array('enabled' => true));
        $this->treatFalseLike(array('enabled' => false));

        $node = parent::createNode();

        $node->addChild($enabledNode = new BooleanNode('enabled'));
        $enabledNode->setDefaultValue(false);

        $filterDef = new ArrayNodeDefinition('filter');
        $filterDef->setBuilder(new NodeBuilder());
        $filterDef
            ->addDefaultsIfNotSet()
            ->children()
                ->arrayNode('paths')
                    ->prototype('scalar')->end()
                ->end()
                ->arrayNode('excluded_paths')
                    ->prototype('scalar')->end()
                ->end()
            ->end()
        ;
        $node->addChild($filterDef->getNode());

        if ($this->perFileConfigDef) {
            $node->addChild($this->perFileConfigDef->getNode());

            $pathConfigDef = new ArrayNodeDefinition('path_configs');
            $pathConfigDef->setBuilder(new NodeBuilder());

            $pathConfigDef
                ->prototype('array')
                    ->children()
                        ->arrayNode('paths')
                            ->requiresAtLeastOneElement()
                            ->prototype('scalar')->end()
                        ->end()
                        ->booleanNode('enabled')
                            ->defaultTrue()
                        ->end()
                        ->append($this->perFileConfigDef)
            ;

            $node->addChild($pathConfigDef->getNode());
        }

        return $node;
    }
}