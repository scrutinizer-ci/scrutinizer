<?php

namespace Scrutinizer;

use Scrutinizer\Config\ConfigBuilder;
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

            $this->builders[] = $configBuilder = new ConfigBuilder($analyzer->getName());
            $analyzer->buildConfig($configBuilder);
        }
    }

    public function process(array $values)
    {
        $processor = new Processor();

        return $processor->process($this->getTree(), array($values));
    }

    public function getTree()
    {
        $tb = new TreeBuilder();

        $rootNode = $tb->root('scrutinizer', 'array');
        $rootNode
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

        foreach ($this->builders as $builder) {
            assert($builder instanceof ConfigBuilder);
            $rootNode->append($builder);
        }

        return $tb->buildTree();
    }
}