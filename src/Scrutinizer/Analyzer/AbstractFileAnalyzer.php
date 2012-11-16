<?php

namespace Scrutinizer\Analyzer;

use Scrutinizer\Analyzer\FileTraversal;
use Scrutinizer\Model\Project;
use Scrutinizer\Model\File;
use Scrutinizer\Config\NodeBuilder;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Scrutinizer\Util\FilesystemInterface;
use Scrutinizer\Util\ProcessExecutorInterface;
use Monolog\Logger;
use Scrutinizer\Config\ConfigBuilder;
use Scrutinizer\Analyzer\FilesystemAwareInterface;
use Scrutinizer\Analyzer\ProcessExecutorAwareInterface;
use Scrutinizer\Analyzer\LoggerAwareInterface;
use Scrutinizer\Analyzer\AnalyzerInterface;

/**
 * Base class for file-based analyzers.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
abstract class AbstractFileAnalyzer implements AnalyzerInterface, LoggerAwareInterface, ProcessExecutorAwareInterface, FilesystemAwareInterface
{
    /** @var Logger */
    protected $logger;

    /** @var ProcessExecutorInterface */
    protected $executor;

    /** @var FilesystemInterface */
    protected $fs;

    public function setLogger(Logger $logger)
    {
        $this->logger = $logger;
    }

    public function setProcessExecutor(ProcessExecutorInterface $executor)
    {
        $this->executor = $executor;
    }

    public function setFilesystem(FilesystemInterface $fs)
    {
        $this->fs = $fs;
    }

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