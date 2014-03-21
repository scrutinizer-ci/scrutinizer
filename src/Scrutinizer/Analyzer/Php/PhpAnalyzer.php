<?php

namespace Scrutinizer\Analyzer\Php;

use Scrutinizer\Analyzer\AbstractFileAnalyzer;
use Scrutinizer\Config\ConfigBuilder;
use Scrutinizer\Model\File;
use Scrutinizer\Model\Project;
use Scrutinizer\PhpAnalyzer\Analyzer;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;

/**
 * Integrates PHP Analyzer.
 *
 * @doc-path tools/php/php-analyzer/
 * @display-name PHP Analyzer
 */
class PhpAnalyzer extends AbstractFileAnalyzer
{
    public function getName()
    {
        return 'php_analyzer';
    }

    public function analyze(Project $project, File $file)
    {
    }

    protected function buildConfigInternal(ConfigBuilder $builder)
    {
        $this->addGeneralConfig($builder);
        $this->addPassConfigs($builder);
    }

    private function addGeneralConfig(ConfigBuilder $builder)
    {
        $builder->globalConfig()
            ->arrayNode('dependency_paths')
                ->info('A list of path patterns if you have embedded dependencies.')
                ->validate()->always(function($v) {
                    $normalized = array();
                    foreach ($v as $pattern) {
                        if (substr($pattern, -1) === '/') {
                            $normalized[] = $pattern.'*';
                            continue;
                        }
                        $normalized[] = $pattern;
                    }

                    return $normalized;
                })->end()
                ->prototype('scalar')->end()
            ->end()
        ;
    }

    private function addPassConfigs(ConfigBuilder $builder)
    {
        if ( ! class_exists('Scrutinizer\PhpAnalyzer\Analyzer')) {
            return;
        }

        $ref = new \ReflectionProperty('Symfony\Component\Config\Definition\Builder\TreeBuilder', 'root');
        $ref->setAccessible(true);

        $pathConfigNode = $builder->perFileConfig('array');
        foreach ((new Analyzer)->getPassConfig()->getConfigurations() as $cfg) {
            /** @var $cfg TreeBuilder */
            $def = $ref->getValue($cfg);
            $pathConfigNode->append($def);
        }
    }

    protected function getInfo()
    {
        return 'Runs Scrutinizer\'s PHP Analyzer Tool';
    }

    protected function getDefaultExtensions()
    {
        return array('php');
    }
}