<?php

namespace Scrutinizer\Analyzer\Php;

use Scrutinizer\Analyzer\AbstractFileAnalyzer;
use Scrutinizer\Analyzer\AnalyzerInterface;
use Scrutinizer\Config\ConfigBuilder;
use Scrutinizer\Model\File;
use Scrutinizer\Model\Project;
use Scrutinizer\PhpAnalyzer\Analyzer;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;

/**
 * Integrates PHP Similarity Analyzer.
 *
 * @doc-path tools/php/code-similarity-analyzer/
 * @display-name PHP Similarity Analyzer
 */
class PhpSimilarityAnalyzer implements AnalyzerInterface
{
    public function getName()
    {
        return 'php_sim';
    }

    public function scrutinize(Project $project)
    {
    }

    public function buildConfig(ConfigBuilder $builder)
    {
        $builder
            ->info('Scans PHP projects for duplicated code.')
            ->globalConfig()
                ->scalarNode('min_mass')
                    ->info('The minimum mass before a code fragment is considered in the analysis.')
                    ->defaultValue(16)
                ->end()
            ->end()
        ;
    }
}