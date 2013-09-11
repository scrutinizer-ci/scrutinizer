<?php

namespace Scrutinizer\Config;

use Scrutinizer\Config\NodeBuilder;
use Symfony\Component\Config\Definition\PrototypedArrayNode;
use Symfony\Component\Config\Definition\BooleanNode;
use Symfony\Component\Config\Definition\ArrayNode;
use Symfony\Component\Config\Definition\Builder\NodeParentInterface;
use Symfony\Component\Config\Definition\Builder\NodeDefinition;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;

/**
 * Specialized builder to enforce a default config structure for analyzers.
 *
 * This adds some predictability to the config structure while also saving us
 * to type that much.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class ConfigBuilder extends ArrayNodeDefinition
{
    private $perFileConfigDef;
    private $addDefaultSettings = true;
    private $addDefaultFilter = true;

    public function children()
    {
        throw new \LogicException('Please use globalConfig() instead.');
    }

    public function disableDefaultSettings()
    {
        $this->addDefaultSettings = false;

        return $this;
    }

    public function disableDefaultFilter()
    {
        $this->addDefaultFilter = false;

        return $this;
    }

    /**
     * @return ConfigBuilder
     */
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

        $node = parent::createNode();

        if ($this->addDefaultSettings) {
            $node->setAddIfNotSet(true);

            $node->setNormalizationClosures(array(
                function($v) {
                    if (is_array($v) && ! array_key_exists('enabled', $v)) {
                        $v['enabled'] = true;
                    }

                    return $v;
                }
            ));

            $ref = new \ReflectionProperty($node, 'equivalentValues');
            $ref->setAccessible(true);
            $ref->setValue($node, array());

            $node->addEquivalentValue(true, array('enabled' => true));
            $node->addEquivalentValue(null, array('enabled' => true));
            $node->addEquivalentValue(false, array('enabled' => false));

            $node->addChild($enabledNode = new BooleanNode('enabled'));
            $enabledNode->setDefaultValue(false);

            if ($this->addDefaultFilter) {
                $filterDef = new ArrayNodeDefinition('filter');
                $filterDef->attribute('show_in_editor', false);
                $filterDef->setBuilder(new NodeBuilder());
                $filterDef
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->arrayNode('paths')
                            ->prototype('file_pattern')->end()
                        ->end()
                        ->arrayNode('excluded_paths')
                            ->prototype('file_pattern')->end()
                        ->end()
                    ->end()
                ;
                $node->addChild($filterDef->getNode());
            }
        }

        if ($this->perFileConfigDef) {
            $node->addChild($this->perFileConfigDef->getNode());

            $pathConfigDef = new ArrayNodeDefinition('path_configs');
            $pathConfigDef->attribute('show_in_editor', false);
            $pathConfigDef->setBuilder(new NodeBuilder());

            $pathConfigDef
                ->prototype('array')
                    ->children()
                        ->arrayNode('paths')
                            ->requiresAtLeastOneElement()
                            ->prototype('file_pattern')->end()
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