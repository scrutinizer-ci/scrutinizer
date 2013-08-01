<?php

namespace Scrutinizer\Analyzer\Custom;

use Scrutinizer\Logger\LoggableProcess;
use Scrutinizer\Model\Project;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\Processor;

class ProjectBasedRunner extends AbstractRunner
{
    private $outputConfigNode;
    private $configProcessor;

    public function __construct()
    {
        $tb = new TreeBuilder();
        $metricsNode = $tb->root('{root}', 'array')
            ->children()
                ->arrayNode('metrics')
                    ->useAttributeAsKey('key')
                    ->validate()->always(function($metrics) {
                        $rs = array();
                        foreach ($metrics as $k => $v) {
                            $rs['custom.'.$k] = $v;
                        }

                        return $rs;
                    })->end()
                    ->prototype('array')
                        ->beforeNormalization()
                            ->ifTrue(function($v) { return ! is_array($v); })
                            ->then(function($v) { return array('value' => $v); })
                        ->end()
                        ->children()
                            ->scalarNode('value')
                                ->validate()
                                    ->always(function($v) {
                                        if ( ! is_int($v) && ! is_double($v)) {
                                            throw new \RuntimeException(sprintf('"value" must be either an integer, or a double, but got "%s".', gettype($v)));
                                        }

                                        return $v;
                                    })
                                ->end()
                            ->end()
                        ->end()
                    ->end()
        ;

        if (method_exists($metricsNode, 'normalizeKeys')) {
            $metricsNode->normalizeKeys(false);
        }
        $this->outputConfigNode = $tb->buildTree();

        $this->configProcessor = new Processor();
    }

    public function run(Project $project, array $commandData)
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'changed-paths');
        file_put_contents($tmpFile, implode("\n", $project->getPaths()));

        $placeholders = array(
            '%path%' => $project->getDir(),
            '%changed_paths_file%' => $tmpFile,
        );

        $cmd = strtr($commandData['command'], $placeholders);

        for ($i=0; $i<$commandData['iterations']; $i++) {
            $proc = new LoggableProcess($cmd);
            $proc->setTimeout(300);
            $proc->setLogger($this->logger);
            $exitCode = $proc->run();

            if (0 === $exitCode) {
                $output = isset($commandData['output_file']) ? file_get_contents($commandData['output_file'])
                    : $proc->getOutput();

                $rawOutput = json_decode($output, true);
                if ( ! is_array($rawOutput)) {
                    throw new \RuntimeException(sprintf('The output of "%s" must be an array, but got: %s', $commandData['command'], $output));
                }

                $parsedOutput = $this->configProcessor->process($this->outputConfigNode, array($rawOutput));
                foreach ($parsedOutput['metrics'] as $k => $data) {
                    if ($commandData['iterations'] === 1) {
                        $project->setSimpleValuedMetric($k, $data['value']);
                    } else {
                        $project->addMetricDataPoint($k, $data['value']);
                    }
                }
            }
        }

        unlink($tmpFile);
    }
}