<?php

namespace Scrutinizer\Analyzer\Php;

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Scrutinizer\Analyzer\AnalyzerInterface;
use Scrutinizer\Util\XmlUtils;
use Scrutinizer\Config\ConfigBuilder;
use Scrutinizer\Model\File;
use Scrutinizer\Model\Project;
use Scrutinizer\Analyzer\AbstractFileAnalyzer;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

/**
 * Integrates LOC Analyzer
 *
 * @doc-path tools/php/lines-of-code/
 * @display-name PHP Lines Of Code
 */
class LocAnalyzer implements AnalyzerInterface, LoggerAwareInterface
{
    use LoggerAwareTrait;

    private static $metrics = array(
        'files' => array(),
        'loc' => array(
            'key' => 'lines_of_code',
        ),
        'lloc' => array(
            'key' => 'logical_lines_of_code',
        ),
        'llocClasses' => array(
            'key' => 'logical_lines_of_code_in_classes',
        ),
        'llocFunctions' => array(
            'key' => 'logical_lines_of_code_in_functions',
        ),
        'llocGlobal' => array(
            'key' => 'logical_lines_of_code_in_global_namespace',
        ),
        'cloc' => array(
            'key' => 'lines_of_comments',
        ),
        'ccn' => array(
            'key' => 'cyclomatic_complexity',
        ),
        'ccnMethods' => array(
            'key' => 'cyclomatic_complexity_in_methods',
        ),
        'interfaces' => array(),
        'traits' => array(),
        'classes' => array(),
        'abstractClasses' => array(
            'key' => 'abstract_classes',
        ),
        'concreteClasses' => array(
            'key' => 'concrete_classes',
        ),
        'functions' => array(),
        'namedFunctions' => array(
            'key' => 'named_functions',
        ),
        'anonymousFunctions' => array(
            'key' => 'anonymous_functions',
        ),
        'methods' => array(),
        'publicMethods' => array(
            'key' => 'public_methods',
        ),
        'nonPublicMethods' => array(
            'key' => 'non_public_methods',
        ),
        'nonStaticMethods' => array(
            'key' => 'non_static_methods',
        ),
        'staticMethods' => array(
            'key' => 'static_methods',
        ),
        'constants' => array(),
        'classConstants' => array(
            'key' => 'class_constants',
        ),
        'globalConstants' => array(
            'key' => 'global_constants',
        ),
        'testClasses' => array(
            'key' => 'test_classes',
        ),
        'testMethods' => array(
            'key' => 'test_methods',
        ),
        'ccnByLloc' => array(
            'type' => 'float',
            'key' => 'average_cyclomatic_complexity_per_logical_line_of_code',
        ),
        'ccnByNom' => array(
            'type' => 'float',
            'key' => 'average_cyclomatic_complexity_per_method',
        ),
        'llocByNoc' => array(
            'type' => 'float',
            'key' => 'average_logical_lines_of_code_per_class',
        ),
        'llocByNom' => array(
            'type' => 'float',
            'key' => 'average_logical_lines_of_code_per_method',
        ),
        'llocByNof' => array(
            'type' => 'float',
            'key' => 'average_logical_lines_of_code_per_function',
        ),
        'methodCalls' => array(
            'key' => 'method_calls',
        ),
        'staticMethodCalls' => array(
            'key' => 'static_method_calls',
        ),
        'instanceMethodCalls' => array(
            'key' => 'instance_method_calls',
        ),
        'attributeAccesses' => array(
            'key' => 'attribute_accesses',
        ),
        'staticAttributeAccesses' => array(
            'key' => 'static_attribute_accesses',
        ),
        'instanceAttributeAccesses' => array(
            'key' => 'instance_attribute_accesses',
        ),
        'globalAccesses' => array(
            'key' => 'global_accesses',
        ),
        'globalVariableAccesses' => array(
            'key' => 'global_variable_accesses',
        ),
        'superGlobalVariableAccesses' => array(
            'key' => 'super_global_variable_accesses',
        ),
        'globalConstantAccesses' => array(
            'key' => 'global_constant_accesses',
        ),
    );

    public function getName()
    {
        return 'php_loc';
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
            ->info('Analyzes the size and structure of a PHP project.')
            ->disableDefaultFilter()
            ->globalConfig()
                ->scalarNode('command')->defaultValue('phploc')->end()
                ->arrayNode('names')
                    ->attribute('help_block', 'A single name pattern per line.')
                    ->defaultValue(array('*.php'))
                    ->prototype('scalar')->end()
                ->end()
                ->arrayNode('excluded_dirs')
                    ->attribute('label', 'Excluded Directories')
                    ->attribute('help_block', 'A single directory per line.')
                    ->prototype('scalar')->end()
                ->end()
            ->end()
        ;
    }    /**
     * Analyzes the given project.
     *
     * @param Project $project
     *
     * @return void
     */
    public function scrutinize(Project $project)
    {
        $outputFile = tempnam(sys_get_temp_dir(), 'phploc-output');
        $command = $project->getGlobalConfig('command').' --progress --log-xml '.escapeshellarg($outputFile);

        $names = $project->getGlobalConfig('names');
        if ( ! empty($names)) {
            $command .= ' --names '.escapeshellarg(implode(',', $names));
        }

        $excludedDirs = $project->getGlobalConfig('excluded_dirs');
        if ( ! empty($excludedDirs)) {
            foreach ($excludedDirs as $excludedDir) {
                $command .= ' --exclude '.escapeshellarg($excludedDir);
            }
        }

        $this->logger->info('$ '.$command."\n");
        $proc = new Process($command.' '.$project->getDir());
        $proc->setTimeout(600);
        $proc->setIdleTimeout(180);
        $proc->setPty(true);
        $proc->run(function($_, $data) {
            $this->logger->info($data);
        });

        $output = file_get_contents($outputFile);
        unlink($outputFile);
        if (0 !== $proc->run()) {
            throw new ProcessFailedException($proc);
        }

        $doc = XmlUtils::safeParse($output);
        foreach (self::$metrics as $name => $metricData) {
            $type = isset($metricData['type']) ? $metricData['type'] : 'integer';
            $key = isset($metricData['key']) ? $metricData['key'] : $name;

            if ( ! isset($doc->$name)) {
                continue;
            }

            $value = $doc->$name;
            settype($value, $type);

            $project->setSimpleValuedMetric($this->getName().'.'.$key, $value);
        }
    }
}
