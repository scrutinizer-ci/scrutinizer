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

/**
 * Base class for file-based analyzers.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
abstract class AbstractFileAnalyzer implements AnalyzerInterface, LoggerAwareInterface
{
    use LoggerAwareTrait;

    public function getMetrics()
    {
        return array();
    }

    protected function addGlobalConfig(NodeBuilder $builder)
    {
    }

    protected function addPerFileConfig(NodeBuilder $builder)
    {
    }

    abstract protected function getInfo();
    abstract protected function getDefaultExtensions();
    abstract public function analyze(Project $project, File $file);

    public function buildConfig(ConfigBuilder $builder)
    {
        $builder->info($this->getInfo());

        $globalConfig = $builder->globalConfig();
        $globalConfig
            ->fixXmlConfig('extension')
            ->arrayNode('extensions')
                ->prototype('scalar')
                    ->defaultValue($this->getDefaultExtensions())
                ->end()
            ->end()
        ;

        $this->addGlobalConfig($globalConfig);
        $this->addPerFileConfig($builder->perFileConfig());
    }

    public function scrutinize(Project $project)
    {
        FileTraversal::create($project, $this, 'analyze')
            ->setExtensions($project->getGlobalConfig('extensions'))
            ->setLogger($this->logger)
            ->traverse();
    }
}