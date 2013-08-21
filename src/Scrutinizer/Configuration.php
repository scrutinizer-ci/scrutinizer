<?php

namespace Scrutinizer;

use Scrutinizer\Config\ConfigBuilder;
use Scrutinizer\Analyzer\AnalyzerInterface;
use Scrutinizer\Config\NodeBuilder;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
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
     * @param AnalyzerInterface[] $analyzers
     */
    public function __construct(array $analyzers)
    {
        foreach ($analyzers as $analyzer) {
            assert($analyzer instanceof AnalyzerInterface);

            $this->builders[] = $configBuilder = new ConfigBuilder($analyzer->getName());
            $analyzer->buildConfig($configBuilder);
        }
    }

    public function processConfigs(array $configs)
    {
        return (new Processor())->process($this->getTree(), $configs);
    }

    public function process(array $values)
    {
        $processor = new Processor();

        return $processor->process($this->getTree(), array($values));
    }

    /**
     * @return ArrayNodeDefinition
     * @throws \Exception
     */
    public function getTree()
    {
        $tb = new TreeBuilder();

        $rootNode = $tb->root('{root}', 'array', new NodeBuilder());
        $rootNode
            ->attribute('artificial', true)
            ->fixXmlConfig('before_command')
            ->fixXmlConfig('after_command')
            ->fixXmlConfig('artifact')
            ->validate()->always(function($v) {
                // Copy over the global filter options if no local filter has been defined for a tool.
                foreach ($v['tools'] as &$tool) {
                    if (isset($tool['filter']) && empty($tool['filter']['paths']) && empty($tool['filter']['excluded_paths'])) {
                        $tool['filter'] = $v['filter'];
                    }
                }

                return $v;
            })->end()
            ->children()
                ->booleanNode('inherit')->defaultFalse()->end()
                ->arrayNode('filter')
                    ->info('Allows you to filter which files are included in the review; by default, all files.')
                    ->fixXmlConfig('path')
                    ->fixXmlConfig('excluded_path')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->arrayNode('paths')
                            ->example("[src/*, tests/*]")
                            ->prototype('file_pattern')->end()
                        ->end()
                        ->arrayNode('excluded_paths')
                            ->example("[tests/*/Fixture/*]")
                            ->prototype('file_pattern')->end()
                        ->end()
                    ->end()
                ->end()

                ->arrayNode('before_commands')
                    ->prototype('scalar')->end()
                ->end()
                ->arrayNode('after_commands')
                    ->prototype('scalar')->end()
                ->end()
                ->arrayNode('artifacts')
                    ->useAttributeAsKey('name')
                    ->prototype('scalar')->end()
                    ->validate()
                        ->always(function($v) {
                            foreach (array_keys($v) as $key) {
                                if ( ! preg_match('/^[a-zA-Z_\-0-9]+$/', $key)) {
                                    throw new \Exception(sprintf('The key "%s" does not match "^[a-zA-Z_\-0-9]+$".', $key));
                                }
                            }

                            return $v;
                        })
                    ->end()
                ->end()
            ->end()
        ;

        $toolNode = $rootNode->children()->arrayNode('tools');
        $toolNode->addDefaultsIfNotSet();
        foreach ($this->builders as $builder) {
            /** @var $builder ConfigBuilder */
            $toolNode->append($builder);
        }

        return $tb->buildTree();
    }
}