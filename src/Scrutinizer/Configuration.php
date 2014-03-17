<?php

namespace Scrutinizer;

use Scrutinizer\BuildConditionDsl\DslParser;
use Scrutinizer\Config\ConfigBuilder;
use Scrutinizer\Analyzer\AnalyzerInterface;
use Scrutinizer\Config\NodeBuilder;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

/**
 * Lays out the structure of the configuration.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class Configuration
{
    private $builders = array();
    private $configRegistry;

    /**
     * @param AnalyzerInterface[] $analyzers
     */
    public function __construct(array $analyzers, DefaultConfigRegistry $configRegistry = null)
    {
        foreach ($analyzers as $analyzer) {
            assert($analyzer instanceof AnalyzerInterface);

            $this->builders[] = $configBuilder = new ConfigBuilder($analyzer->getName());
            $analyzer->buildConfig($configBuilder);
        }

        $this->configRegistry = $configRegistry ?: new DefaultConfigRegistry();
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
                ->arrayNode('imports')
                    ->prototype('scalar')
                        ->validate()->always(function($filename) {
                            if (substr($filename, -4) === '.yml') {
                                $filename = substr($filename, 0, -4);
                            }

                            $availableConfigs = $this->configRegistry->getAvailableConfigs();

                            if ( ! in_array($filename, $availableConfigs, true)) {
                                throw new \Exception(sprintf(
                                    'The default config "%s" does not exist. Available configs: %s',
                                    $filename,
                                    implode(', ', $availableConfigs)
                                ));
                            }

                            return $filename;
                        })->end()
                    ->end()
                ->end()
                ->arrayNode('filter')
                    ->info('Allows you to filter which files are included in the review; by default, all files.')
                    ->fixXmlConfig('path')
                    ->fixXmlConfig('excluded_path')
                    ->performNoDeepMerging()
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

                ->arrayNode('changetracking')
                    ->info('Allows you to configure settings for tracking changes to your code like patterns for bug/feature commits.')
                    ->fixXmlConfig('bug_pattern')
                    ->fixXmlConfig('feature_pattern')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->arrayNode('bug_patterns')
                            ->defaultValue(array('\bfix(?:es|ed)?\b'))
                            ->prototype('scalar')->end()
                        ->end()
                        ->arrayNode('feature_patterns')
                            ->defaultValue(array('\badd(?:s|ed)?\b', '\bimplement(?:s|ed)?\b'))
                            ->prototype('scalar')->end()
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

                ->arrayNode('build_failure_conditions')
                    ->useAttributeAsKey('build_failure_condition')
                    ->performNoDeepMerging()
                    ->validate()
                        ->always(function(array $v) {
                            if (class_exists('Scrutinizer\\BuildConditionDsl\\DslParser')) {
                                $parser = new DslParser();
                                foreach ($v as $cond) {
                                    $parser->parse($cond, 'in "'.$cond.'"');
                                }
                            }

                            return $v;
                         })
                    ->end()
                    ->prototype('scalar')->end()
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