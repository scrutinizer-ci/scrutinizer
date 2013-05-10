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
        $tb->root('{root}', 'array')
            ->children()
                ->arrayNode('metrics')
                    ->normalizeKeys(false)
                    ->useAttributeAsKey('key')
                    ->validate()->always(function($metrics) {
                        $rs = array();
                        foreach ($metrics as $k => $v) {
                            $rs['my-'.$k] = $v;
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
                ->end()
            ->end()
        ;
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
        $proc = new LoggableProcess($cmd);
        $proc->setLogger($this->logger);
        $exitCode = $proc->run();

        unlink($tmpFile);

        if (0 === $exitCode) {
            $output = isset($customAnalyzer['output_file']) ? file_get_contents($customAnalyzer['output_file'])
                : $proc->getOutput();

            $rawOutput = json_decode($output, true);
            if ( ! is_array($rawOutput)) {
                throw new \RuntimeException(sprintf('The output of "%s" must be an array, but got: %s', $commandData['command'], $output));
            }

            $parsedOutput = $this->configProcessor->process($this->outputConfigNode, array($rawOutput));
            foreach ($parsedOutput['metrics'] as $k => $data) {
                $project->setSimpleValuedMetric($k, $data['value']);
            }
        }
    }
}