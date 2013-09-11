<?php

namespace Scrutinizer\Analyzer;

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Scrutinizer\Analyzer\FileTraversal;
use Scrutinizer\Model\Project;
use Scrutinizer\Model\File;
use Scrutinizer\Config\NodeBuilder;
use Scrutinizer\Config\ConfigBuilder;
use Scrutinizer\Analyzer\AnalyzerInterface;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\NodeDefinition;

/**
 * Base class for file-based analyzers.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
abstract class AbstractFileAnalyzer implements AnalyzerInterface, LoggerAwareInterface
{
    use LoggerAwareTrait;

    abstract protected function getInfo();
    abstract protected function getDefaultExtensions();
    abstract public function analyze(Project $project, File $file);

    protected function buildConfigInternal(ConfigBuilder $builder)
    {
    }

    public final function buildConfig(ConfigBuilder $builder)
    {
        $builder
            ->info($this->getInfo())
            ->globalConfig()
                ->arrayNode('extensions')
                    ->attribute('show_in_editor', false)
                    ->prototype('scalar')->end()
                    ->defaultValue($this->getDefaultExtensions())
                ->end()
            ->end()
        ;

        $this->buildConfigInternal($builder);
    }

    public function scrutinize(Project $project)
    {
        FileTraversal::create($project, $this, 'analyze')
            ->setExtensions($project->getGlobalConfig('extensions'))
            ->setLogger($this->logger)
            ->traverse();
    }
}
