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
                ->scalarNode('timeout')
                    ->validate()->always(function($v) {
                        $v = (integer) $v;
                        if ($v < 60 || $v > 3600) {
                            throw new \Exception('The timeout must be in the interval [60,3600].');
                        }

                        return $v;
                    })->end()
                    ->defaultValue(300)
                ->end()
            ->end()
        ;
    }

    public function scrutinize(Project $project)
    {
    }
}
