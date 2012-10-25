<?php

namespace Scrutinizer;

use Scrutinizer\Analyzer\AnalyzerInterface;
use Scrutinizer\Config\NodeBuilder;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\Processor;

/**
 * Lays out the structure of the configuration.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class Configuration
{
    private $builders = array();

    /**
     * @param array<AnalyzerInterface> $analyzers
     */
    public function __construct(array $analyzers)
    {
        foreach ($analyzers as $analyzer) {
            assert($analyzer instanceof AnalyzerInterface);
            $this->builders[] = $analyzer->getConfigBuilder();
        }
    }

    public function process(array $values)
    {
        $configs = array($values);
        $processor = new Processor();
        $defaultConfig = $processor->process($this->getDefaultConfig(), $configs);

        if (isset($values['path_configs']) && is_array($values['path_configs'])) {
            $defaultPathConfigs = array();
            foreach (array_keys($values['path_configs']) as $name) {
                $defaultPathConfigs['path_configs'][$name] = $defaultConfig;
            }

            array_unshift($configs, $defaultPathConfigs);
        }

        return $processor->process($this->getCompleteConfig(), $configs);
    }

    public function getDefaultConfig()
    {
        $tb = new TreeBuilder();
        $rootNode = $tb->root('scrutinizer', 'array')
            ->ignoreExtraKeys();

        $this->addDefaultConfig($rootNode);

        return $tb->buildTree();
    }

    public function getCompleteConfig()
    {
        $tb = new TreeBuilder();

        $rootNode = $tb->root('scrutinizer', 'array');
        $rootNode
            ->fixXmlConfig('path_config')
            ->children()
                ->arrayNode('filter')
                    ->info('Allows you to filter which files are included in the review; by default, all files.')
                    ->fixXmlConfig('path')
                    ->fixXmlConfig('excluded_path')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->arrayNode('paths')
                            ->example("[src/*, tests/*]")
                            ->info('Patterns must match the entire path to apply; "src/" will not match "src/foo".')
                            ->prototype('scalar')->end()
                        ->end()
                        ->arrayNode('excluded_paths')
                            ->example("[tests/*/Fixture/*]")
                            ->prototype('scalar')->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;

        $this->addDefaultConfig($rootNode);

        $pathConfigBuilder = $rootNode
            ->children()
                ->arrayNode('path_configs')
                    ->info('Overwrites the default config for specific paths; keys of prototypes are not meaningful.')
                    ->useAttributeAsKey('name')
                    ->prototype('array')
                        ->fixXmlConfig('path')
                        ->children()
                            ->arrayNode('paths')
                                ->info('The paths to which this config applies. Patterns must match the entire path (see filter above).')
                                ->example('[tests/*]')
                                ->prototype('scalar')->cannotBeEmpty()->end()
                            ->end()
        ;

        foreach ($this->builders as $builder) {
            $pathConfigBuilder->append($builder->getRoot());
        }

        return $tb->buildTree();
    }

    private function addDefaultConfig(NodeBuilder $rootNode)
    {
        $defaultConfigBuilder = $rootNode
            ->children()
                ->arrayNode('default_config')
                    ->addDefaultsIfNotSet()
                    ->info('The default config is a base config which may be overwritten for specific paths (see below).')
                    ->children()
        ;

        foreach ($this->builders as $builder) {
            $defaultConfigBuilder->append($builder->getRoot());
        }
    }
}