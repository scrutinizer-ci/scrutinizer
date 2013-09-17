<?php

namespace Scrutinizer\Analyzer;

use Scrutinizer\Config\ConfigBuilder;
use Scrutinizer\Model\File;
use Scrutinizer\Model\Project;
use Scrutinizer\PhpAnalyzer\Analyzer;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;

/**
 * Configuration for external code coverage.
 *
 * @doc-path tools/external-code-coverage/
 * @display-name External Code Coverage
 */
class ExternalCodeCoverageAnalyzer implements AnalyzerInterface
{
    public function getName()
    {
        return 'external_code_coverage';
    }

    public function buildConfig(ConfigBuilder $builder)
    {
        $builder
            ->info('Allows to incorporate code coverage information provided by an external service.')
            ->disableDefaultSettings()
            ->globalConfig()
                ->scalarNode('timeout')->defaultValue(300)->end()
            ->end()
        ;
    }

    public function scrutinize(Project $project)
    {
    }
}