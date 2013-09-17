<?php

namespace Scrutinizer\Analyzer\Php;

use JMS\PhpManipulator\TokenStream;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Scrutinizer\Analyzer\AnalyzerInterface;
use Scrutinizer\Analyzer\Php\Util\CodeCoverageProcessor;
use Scrutinizer\Analyzer\Php\Util\ImpactAnalyzer;
use Scrutinizer\Config\ConfigBuilder;
use Scrutinizer\Model\File;
use Scrutinizer\Model\Location;
use Scrutinizer\Model\Project;
use Scrutinizer\Util\PathUtils;
use Scrutinizer\Util\XmlUtils;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

/**
 * @display-name PHP Code Coverage
 * @doc-path tools/php/code-coverage/
 */
class CodeCoverageAnalyzer implements AnalyzerInterface, LoggerAwareInterface
{
    use LoggerAwareTrait;

    private $coverageProcessor;

    public function __construct()
    {
        $this->coverageProcessor = new CodeCoverageProcessor();
    }

    public function scrutinize(Project $project)
    {
        if ( ! extension_loaded('xdebug')) {
            throw new \LogicException('The xdebug extension must be loaded for generating code coverage.');
        }

        if ($project->getGlobalConfig('only_changesets')) {
            $this->logger->info('The "only_changesets" option for "php_code_coverage" was deprecated.'."\n");
        }

        $outputFile = tempnam(sys_get_temp_dir(), 'php-code-coverage');
        $testCommand = $project->getGlobalConfig('test_command').' --coverage-clover '.escapeshellarg($outputFile);
        $this->logger->info(sprintf('Running command "%s"...'."\n", $testCommand));
        $proc = new Process($testCommand, $project->getDir());
        $proc->setTimeout(1800);
        $proc->setIdleTimeout(300);
        $proc->setPty(true);
        $proc->run(function($_, $data) {
            $this->logger->info($data);
        });

        $output = file_get_contents($outputFile);
        unlink($outputFile);

        if (empty($output)) {
            if ($proc->getExitCode() > 0) {
                throw new ProcessFailedException($proc);
            }

            return;
        }

        $this->coverageProcessor->processCloverFile($project, $output);
    }

    public function buildConfig(ConfigBuilder $builder)
    {
        $builder
            ->info('Collects code coverage information about the changeset.')
            ->globalConfig()
                ->scalarNode('test_command')
                    ->attribute('label', 'Command')
                    ->defaultValue('phpunit')
                ->end()
                ->scalarNode('config_path')
                    ->attribute('label', 'Configuration')
                    ->attribute('help_inline', 'Path to the PHPUnit configuration file (relative to your project\'s root directory).')
                    ->defaultNull()
                ->end()
                ->booleanNode('only_changesets')
                    ->attribute('show_in_editor', false)
                    ->info('(deprecated) Whether code coverage information should only be generated for changesets.')
                    ->defaultFalse()
                ->end()
            ->end()
        ;
    }

    public function getName()
    {
        return 'php_code_coverage';
    }
}