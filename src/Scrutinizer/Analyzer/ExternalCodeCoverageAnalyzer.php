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
            ->globalConfig()
                ->scalarNode('timeout')
                    ->attribute('help_inline', 'The amount of time to wait for coverage data (in seconds).')
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
        $project->getFile('.scrutinizer/coverage/format')->forAll(function(File $file) use ($project) {
            $format = $file->getContent();

            $project->getFile('.scrutinizer/coverage/data')->forAll(function(File $file) use ($project, $format) {
                $this->processCodeCoverage($project, $format, $file->getContent());
            });
        });
    }

    private function processCodeCoverage(Project $project, $format, $data)
    {
        switch ($format) {
            case 'php-clover':
                (new Php\Util\CodeCoverageProcessor())->processCloverFile($project, $data);
                break;

            default:
                throw new \LogicException(sprintf('The coverage format "%s" is unknown.', $format));
        }
    }
}
