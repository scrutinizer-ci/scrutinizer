<?php

namespace Scrutinizer\Analyzer\Php;

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Scrutinizer\Analyzer\AnalyzerInterface;
use Scrutinizer\Config\ConfigBuilder;
use Scrutinizer\Model\Location;
use Scrutinizer\Model\Project;
use Scrutinizer\Util\XmlUtils;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

/**
 * Gathers metrics using PHP PDepend.
 *
 * @doc-path tools/php/pdepend/
 * @display-name PHP PDepend
 */
class PDependAnalyzer implements AnalyzerInterface, LoggerAwareInterface
{
    use LoggerAwareTrait;

    public function getName()
    {
        return 'php_pdepend';
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
                ->scalarNode('command')->defaultValue('pdepend')->end()
                ->scalarNode('configuration_file')
                    ->attribute('show_in_editor', false)
                    ->attribute('help_inline', 'Path to a pdepend configuration file if available (relative to your project\'s root directory).')
                    ->defaultNull()
                ->end()
                ->arrayNode('suffixes')
                    ->validate()->always(function(array $v) {
                        foreach ($v as $k => $suffix) {
                            if (preg_match('/\.([^.]+)$/', $suffix, $match)) {
                                $v[$k] = $match[1];
                            }
                        }

                        return array_unique($v);
                    })->end()
                    ->attribute('show_in_editor', false)
                    ->attribute('help_block', 'One suffix without preceding "*." per line.')
                    ->defaultValue(array('php'))
                    ->prototype('scalar')->end()
                ->end()
                ->arrayNode('excluded_dirs')
                    ->attribute('label', 'Excluded Directories')
                    ->attribute('help_block', 'A single directory without path or ending "/" per line.')
                    ->prototype('scalar')->end()
                    ->defaultValue(array('vendor'))
                ->end()
            ->end()
        ;
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
        $outputFile = tempnam(sys_get_temp_dir(), 'pdepend-output');
        $command = $project->getGlobalConfig('command').' --summary-xml='.escapeshellarg($outputFile);

        if (null !== $configFile = $project->getGlobalConfig('configuration_file')) {
            $command .= ' --configuration='.escapeshellarg($configFile);
        }

        $suffixes = $project->getGlobalConfig('suffixes');
        if ( ! empty($suffixes)) {
            $command .= ' --suffix='.escapeshellarg(implode(',', $suffixes));
        }

        $excludedDirs = $project->getGlobalConfig('excluded_dirs');
        if ( ! empty($excludedDirs)) {
            $command .= ' --ignore='.escapeshellarg(implode(',', $excludedDirs));
        }

        $proc = new Process($command.' '.$project->getDir());
        $proc->setTimeout(1800);
        $proc->setIdleTimeout(300);
        $proc->setPty(true);
        $proc->run(function($_, $data) {
            $this->logger->info($data);
        });

        $output = file_get_contents($outputFile);
        unlink($outputFile);
        if (0 !== $proc->run()) {
            throw new ProcessFailedException($proc);
        }

        /**
         *   <package name="Scrutinizer\PhpAnalyzer" cr="0.2484375" noc="3" nof="0" noi="1" nom="25" rcr="0.2775">
        <class name="Analyzer" ca="19" cbo="19" ce="19" cis="11" cloc="34" cr="0.15" csz="16" dit="0" eloc="104" impl="0" lloc="66" loc="189" ncloc="155" noam="0" nocc$
        <file name="/home/johannes/workspace/php-analyzer/src/Scrutinizer/PhpAnalyzer/Analyzer.php"/>
        <method name="create" ccn="1" ccn2="1" cloc="0" eloc="5" lloc="3" loc="7" ncloc="7" npath="1"/>
        <method name="__construct" ccn="4" ccn2="4" cloc="0" eloc="5" lloc="3" loc="6" ncloc="6" npath="125"/>
        <method name="getPackageVersions" ccn="1" ccn2="1" cloc="0" eloc="3" lloc="1" loc="4" ncloc="4" npath="1"/>
        <method name="setRootPackageVersion" ccn="3" ccn2="3" cloc="0" eloc="14" lloc="10" loc="18" ncloc="18" npath="3"/>
        <method name="setPackageVersions" ccn="1" ccn2="1" cloc="0" eloc="4" lloc="2" loc="5" ncloc="5" npath="1"/>
        <method name="setConfigurationValues" ccn="2" ccn2="2" cloc="0" eloc="10" lloc="7" loc="13" ncloc="13" npath="2"/>
        <method name="setLogger" ccn="1" ccn2="1" cloc="0" eloc="3" lloc="1" loc="4" ncloc="4" npath="1"/>
        <method name="getTypeRegistry" ccn="1" ccn2="1" cloc="0" eloc="3" lloc="1" loc="4" ncloc="4" npath="1"/>
        <method name="getPassConfig" ccn="1" ccn2="1" cloc="0" eloc="3" lloc="1" loc="4" ncloc="4" npath="1"/>
        <method name="analyze" ccn="16" ccn2="18" cloc="8" eloc="54" lloc="37" loc="78" ncloc="70" npath="2946"/>
        </class>
         */

        $doc = new \DOMDocument('1.0', 'utf8');
        $doc->loadXml($output);
        $xpath = new \DOMXPath($doc);

        $metricsNode = $xpath->query('//metrics')->item(0);
        $project->setSimpleValuedMetric('pdepend.average_hierarchy_height', (double) $metricsNode->getAttribute('ahh'));
        $project->setSimpleValuedMetric('pdepend.average_number_of_derived_classes', (double) $metricsNode->getAttribute('andc'));
        $project->setSimpleValuedMetric('pdepend.calls', (integer) $metricsNode->getAttribute('calls'));
        $project->setSimpleValuedMetric('pdepend.cyclomatic_complexity_number', (integer) $metricsNode->getAttribute('ccn'));
        $project->setSimpleValuedMetric('pdepend.extended_cyclomatic_complexity_number', (integer) $metricsNode->getAttribute('ccn2'));
        $project->setSimpleValuedMetric('pdepend.comment_lines_of_code', (integer) $metricsNode->getAttribute('cloc'));
        $project->setSimpleValuedMetric('pdepend.number_of_abstract_classes', (integer) $metricsNode->getAttribute('clsa'));
        $project->setSimpleValuedMetric('pdepend.number_of_concrete_classes', (integer) $metricsNode->getAttribute('clsc'));
        $project->setSimpleValuedMetric('pdepend.executable_lines_of_code', (integer) $metricsNode->getAttribute('eloc'));
        $project->setSimpleValuedMetric('pdepend.number_of_referenced_classes', (integer) $metricsNode->getAttribute('fanout'));
        $project->setSimpleValuedMetric('pdepend.number_of_leaf_classes', (integer) $metricsNode->getAttribute('leafs'));
        $project->setSimpleValuedMetric('pdepend.logical_lines_of_code', (integer) $metricsNode->getAttribute('lloc'));
        $project->setSimpleValuedMetric('pdepend.lines_of_code', (integer) $metricsNode->getAttribute('loc'));
        $project->setSimpleValuedMetric('pdepend.maximum_depth_of_inheritance_tree', (integer) $metricsNode->getAttribute('maxDIT'));
        $project->setSimpleValuedMetric('pdepend.non_comment_lines_of_code', (integer) $metricsNode->getAttribute('ncloc'));
        $project->setSimpleValuedMetric('pdepend.number_of_classes', (integer) $metricsNode->getAttribute('noc'));
        $project->setSimpleValuedMetric('pdepend.number_of_functions', (integer) $metricsNode->getAttribute('nof'));
        $project->setSimpleValuedMetric('pdepend.number_of_interfaces', (integer) $metricsNode->getAttribute('noi'));
        $project->setSimpleValuedMetric('pdepend.number_of_methods', (integer) $metricsNode->getAttribute('nom'));
        $project->setSimpleValuedMetric('pdepend.number_of_packages', (integer) $metricsNode->getAttribute('nop'));
        $project->setSimpleValuedMetric('pdepend.roots', (integer) $metricsNode->getAttribute('roots'));

        foreach ($xpath->query('//package') as $packageNode) {
            /** @var \DOMElement $packageName */
            $packageName = (string) $packageNode->getAttribute('name');
            if (empty($packageName)) {
                $packageName = '+global';
            }

            $package = $project->getOrCreateCodeElement('package', $packageName);
            $package->setMetric('pdepend.code_rank', (double) $packageNode->getAttribute('cr'));
            $package->setMetric('pdepend.number_of_classes', (integer) $packageNode->getattribute('noc'));
            $package->setMetric('pdepend.number_of_functions', (integer) $packageNode->getAttribute('nof'));
            $package->setMetric('pdepend.number_of_interfaces', (integer) $packageNode->getAttribute('noi'));
            $package->setMetric('pdepend.number_of_methods', (integer) $packageNode->getAttribute('nom'));
            $package->setMetric('pdepend.reverse_code_rank', (double) $packageNode->getAttribute('rcr'));

            foreach ($xpath->query('./class', $packageNode) as $classNode) {
                /** @var \DOMElement $classNode */

                $className = $packageName.'\\'.$classNode->getAttribute('name');
                $class = $project->getOrCreateCodeElement('class', $className);
                $package->addChild($class);

                $filename = $xpath->query('./file', $classNode)->item(0)->getAttribute('name');
                $location = new Location(substr($filename, strlen($project->getDir()) + 1));
                $class->setLocation($location);

                $class->setMetric('pdepend.afferent_coupling', (integer) $classNode->getAttribute('ca'));
                $class->setMetric('pdepend.coupling_between_calls', (integer) $classNode->getAttribute('cbo'));
                $class->setMetric('pdepend.efferent_coupling', (integer) $classNode->getAttribute('ce'));
                $class->setMetric('pdepend.class_interface_size', (integer) $classNode->getAttribute('cis'));
                $class->setMetric('pdepend.comment_lines_of_code', (integer) $classNode->getAttribute('cloc'));
                $class->setMetric('pdepend.code_rank', (double) $classNode->getAttribute('cr'));
                $class->setMetric('pdepend.class_size', (integer) $classNode->getAttribute('csz'));
                $class->setMetric('pdepend.depth_of_inheritance_tree', (integer) $classNode->getAttribute('dit'));
                $class->setMetric('pdepend.executable_lines_of_code', (integer) $classNode->getAttribute('eloc'));
                $class->setMetric('pdepend.impl', (integer) $classNode->getAttribute('impl'));
                $class->setMetric('pdepend.logical_lines_of_code', (integer) $classNode->getAttribute('lloc'));
                $class->setMetric('pdepend.lines_of_code', (integer) $classNode->getAttribute('loc'));
                $class->setMetric('pdepend.non_comment_lines_of_code', (integer) $classNode->getAttribute('ncloc'));
                $class->setMetric('pdepend.number_of_added_methods', (integer) $classNode->getAttribute('noam'));
                $class->setMetric('pdepend.number_of_child_classes', (integer) $classNode->getAttribute('nocc'));
                $class->setMetric('pdepend.number_of_methods', (integer) $classNode->getAttribute('nom'));
                $class->setMetric('pdepend.number_of_overwritten_methods', (integer) $classNode->getAttribute('noom'));
                $class->setMetric('pdepend.number_of_public_methods', (integer) $classNode->getAttribute('npm'));
                $class->setMetric('pdepend.reverse_code_rank', (double) $classNode->getAttribute('rcr'));
                $class->setMetric('pdepend.number_of_properties', (integer) $classNode->getAttribute('vars'));
                $class->setMetric('pdepend.number_of_inherited_properties', (integer) $classNode->getAttribute('varsi'));
                $class->setMetric('pdepend.number_of_non_private_properties', (integer) $classNode->getAttribute('varsnp'));
                $class->setMetric('pdepend.weighted_method_count', (integer) $classNode->getAttribute('wmc'));
                $class->setMetric('pdepend.inherited_weighted_method_count', (integer) $classNode->getAttribute('wmci'));
                $class->setMetric('pdepend.non_private_weighted_method_count', (integer) $classNode->getAttribute('wmcnp'));

                foreach ($xpath->query('./method', $classNode) as $methodNode) {
                    /** @var \DOMElement $methodNode */

                    $methodName = $className.'::'.$methodNode->getAttribute('name');
                    $method = $project->getOrCreateCodeElement('operation', $methodName);
                    $method->setLocation($location);
                    $class->addChild($method);

                    $method->setMetric('pdepend.cyclomatic_complexity_number', (integer) $methodNode->getAttribute('ccn'));
                    $method->setMetric('pdepend.extended_cyclomatic_complexity_number', (integer) $methodNode->getAttribute('ccn2'));
                    $method->setMetric('pdepend.comment_lines_of_code', (integer) $methodNode->getAttribute('cloc'));
                    $method->setMetric('pdepend.executable_lines_of_code', (integer) $methodNode->getAttribute('eloc'));
                    $method->setMetric('pdepend.logical_lines_of_code', (integer) $methodNode->getAttribute('lloc'));
                    $method->setMetric('pdepend.lines_of_code', (integer) $methodNode->getAttribute('loc'));
                    $method->setMetric('pdepend.non_comment_lines_of_code', (integer) $methodNode->getAttribute('ncloc'));
                    $method->setMetric('pdepend.npath_complexity', (integer) $methodNode->getAttribute('npath'));
                }
            }
        }
    }
}
