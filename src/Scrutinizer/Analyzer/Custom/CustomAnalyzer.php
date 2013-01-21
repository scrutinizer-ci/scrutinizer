<?php

namespace Scrutinizer\Analyzer\Custom;

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Scrutinizer\Analyzer\AnalyzerInterface;
use Scrutinizer\Config\ConfigBuilder;
use Scrutinizer\Logger\LoggableProcess;
use Scrutinizer\Model\Project;

/**
 * Runs custom tool commands and analyzes the results.
 *
 * We support different output formats; all of which you can find in the Parser/ sub-namespace.
 *
 * @doc-path tools/custom/
 * @display-name Custom
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class CustomAnalyzer implements AnalyzerInterface, LoggerAwareInterface
{
    use LoggerAwareTrait;

    private $parser;

    public function __construct()
    {
        $this->parser = new ParserManager();
    }

    /**
     * Analyzes the given project.
     *
     * @param Project $project
     *
     * @return void
     */
    public function scrutinize(Project $project)
    {
        foreach ($project->getAnalyzerConfig() as $customAnalyzer) {
            $proc = new LoggableProcess($customAnalyzer['command'], $project->getDir());
            $proc->setLogger($this->logger);
            if (0 === $proc->run()) {
                $output = isset($customAnalyzer['output_file']) ? file_get_contents($customAnalyzer['output_file'])
                            : $proc->getOutput();

                $this->parser->parse($project, $customAnalyzer['format'], $output);
            }
        }
    }

    /**
     * Builds the configuration structure of this analyzer.
     *
     * This is comparable to Symfony2's default builders except that the
     * ConfigBuilder does add a unified way to enable and disable analyzers,
     * and also provides a unified basic structure for all analyzers.
     *
     * You can read more about how to define your configuration at
     * http://symfony.com/doc/current/components/config/definition.html
     *
     * @param ConfigBuilder $builder
     *
     * @return void
     */
    public function buildConfig(ConfigBuilder $builder)
    {
        $builder
            ->prototype('array')
                ->children()
                    ->booleanNode('enabled')->defaultTrue()->end()
                    ->scalarNode('command')->isRequired()->end()
                    ->scalarNode('output_file')->end()
                    ->enumNode('format')->isRequired()->values($this->parser->getSupportedFormats())->end()
                ->end()
            ->end()
        ;
    }

    /**
     * Returns metadata for metrics that are measured by this analyzer.
     *
     * @return array<Metric>
     */
    public function getMetrics()
    {
        return array();
    }

    /**
     * The name of this analyzer.
     *
     * Should be a lower-case string with "_" as separators.
     *
     * @return string
     */
    public function getName()
    {
        return 'custom';
    }
}