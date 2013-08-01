<?php

namespace Scrutinizer\Analyzer\Php;

use Scrutinizer\Analyzer\AnalyzerInterface;
use Scrutinizer\Analyzer\Php\Util\ImpactAnalyzer;
use Scrutinizer\Config\ConfigBuilder;
use Scrutinizer\Model\File;
use Scrutinizer\Model\Project;
use Scrutinizer\Util\XmlUtils;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

/**
 * @display-name PHP Code Coverage
 * @doc-path tools/php/code-coverage/
 */
class CodeCoverageAnalyzer implements AnalyzerInterface
{
    private $impactAnalyzer;

    public function __construct()
    {
        $this->impactAnalyzer = new ImpactAnalyzer();
    }

    public function scrutinize(Project $project)
    {
        if ( ! extension_loaded('xdebug')) {
            throw new \LogicException('The xdebug extension must be loaded for generating code coverage.');
        }

        if (null !== $phpunitConfig = $this->findPhpUnitConfig($project)) {
            $paths = $project->getPaths();
            if ( ! empty($paths)) {
                $affectedFiles = $this->impactAnalyzer->findAffectedFiles($project->getDir(), $paths);
                $this->modifyListenerConfig($phpunitConfig, $project->getDir().'/', array_merge($affectedFiles, $paths));
            } elseif ($project->getGlobalConfig('only_changesets')) {
                return;
            }
        }

        $outputFile = tempnam(sys_get_temp_dir(), 'php-code-coverage');
        $testCommand = $project->getGlobalConfig('test_command').' --coverage-clover '.escapeshellarg($outputFile);
        $proc = new Process($testCommand, $project->getDir());
        $proc->run();

        $output = file_get_contents($outputFile);
        unlink($outputFile);

        if (empty($output)) {
            if ($proc->getExitCode() > 0) {
                throw new ProcessFailedException($proc);
            }

            return;
        }

        $this->processCloverFile($project, $output);
    }

    public function buildConfig(ConfigBuilder $builder)
    {
        $builder
            ->info('Collects code coverage information about the changeset.')
            ->globalConfig()
                ->scalarNode('config_path')->defaultNull()->end()
                ->scalarNode('test_command')->defaultValue('phpunit')->end()
                ->booleanNode('only_changesets')
                    ->info('Whether code coverage information should only be generated for changesets.')
                    ->defaultFalse()
                ->end()
            ->end()
        ;
    }

    public function getName()
    {
        return 'php_code_coverage';
    }

    private function processCloverFile(Project $project, $content)
    {
        $doc = XmlUtils::safeParse($content);
        $rootDir = $project->getDir().'/';
        $prefixLength = strlen($rootDir);

        foreach ($doc->xpath('//file') as $xmlFile) {
            if ( ! isset($xmlFile->line)) {
                continue;
            }

            $project->getFile(substr((string) $xmlFile->attributes()->name, $prefixLength))->map(
                function(File $modelFile) use ($xmlFile) {
                    foreach ($xmlFile->line as $line) {
                        $attrs = $line->attributes();
                        $modelFile->setLineAttribute((integer) $attrs->num, 'coverage_count', (integer) $attrs->count);
                    }
                }
            );
        }

        // files="3" loc="114" ncloc="114" classes="3" methods="16" coveredmethods="3" conditionals="0" coveredconditionals="0"
        // statements="38" coveredstatements="5" elements="54" coveredelements="8"
        foreach ($doc->xpath('descendant-or-self::project/metrics') as $metricsNode) {
            $metricsAttrs = $metricsNode->attributes();

            $project->setSimpleValuedMetric('php_code_coverage.files', (integer) $metricsAttrs->files);
            $project->setSimpleValuedMetric('php_code_coverage.lines_of_code', (integer) $metricsAttrs->loc);
            $project->setSimpleValuedMetric('php_code_coverage.non_comment_lines_of_code', (integer) $metricsAttrs->ncloc);
            $project->setSimpleValuedMetric('php_code_coverage.classes', (integer) $metricsAttrs->classes);
            $project->setSimpleValuedMetric('php_code_coverage.methods', (integer) $metricsAttrs->methods);
            $project->setSimpleValuedMetric('php_code_coverage.covered_methods', (integer) $metricsAttrs->coveredmethods);
            $project->setSimpleValuedMetric('php_code_coverage.conditionals', (integer) $metricsAttrs->conditionals);
            $project->setSimpleValuedMetric('php_code_coverage.covered_conditionals', (integer) $metricsAttrs->coveredconditionals);
            $project->setSimpleValuedMetric('php_code_coverage.statements', (integer) $metricsAttrs->statements);
            $project->setSimpleValuedMetric('php_code_coverage.covered_statements', (integer) $metricsAttrs->coveredstatements);
            $project->setSimpleValuedMetric('php_code_coverage.elements', (integer) $metricsAttrs->elements);
            $project->setSimpleValuedMetric('php_code_coverage.covered_elements', (integer) $metricsAttrs->coveredelements);
        }

        /**
         *     <package name="Foo">
                <file name="/tmp/scrtnzerI2LxkB/src/Bar.php">
                <class name="Bar" namespace="Foo">
                <metrics methods="2" coveredmethods="1" conditionals="0" coveredconditionals="0" statements="3"
         *              coveredstatements="2" elements="5" coveredelements="3"/>
                </class>
                <line num="9" type="method" name="__construct" crap="1" count="1"/>
                <line num="11" type="stmt" count="1"/>
                <line num="12" type="stmt" count="1"/>
                <line num="14" type="method" name="getName" crap="2" count="0"/>
                <line num="16" type="stmt" count="0"/>
                <metrics loc="17" ncloc="17" classes="1" methods="2" coveredmethods="1" conditionals="0" coveredconditionals="0" statements="3" coveredstatements="2" elements="5" coveredelements="3"/>
                </file>
         */
        foreach ($doc->xpath('//package') as $packageNode) {
            $packageName = (string) $packageNode->attributes()->name;

            $package = $project->getOrCreateCodeElement('package', $packageName);

            foreach ($packageNode->xpath('./file') as $fileNode) {
                $filename = substr($fileNode->attributes()->name, strlen($project->getDir()) + 1);

                $addedMethods = 0;
                foreach ($fileNode->xpath('./class') as $classNode) {
                    $className = $packageName.'\\'.$classNode->attributes()->name;

                    $class = $project->getOrCreateCodeElement('class', $className);
                    $package->addChild($class);

                    $class->setLocation($filename);

                    $metricsAttrs = $classNode->metrics->attributes();
                    $class->setMetric('php_code_coverage.methods', (integer) $metricsAttrs->methods);
                    $class->setMetric('php_code_coverage.covered_methods', (integer) $metricsAttrs->coveredmethods);
                    $class->setMetric('php_code_coverage.conditionals', (integer) $metricsAttrs->conditionals);
                    $class->setMetric('php_code_coverage.covered_conditionals', (integer) $metricsAttrs->coveredconditionals);
                    $class->setMetric('php_code_coverage.statements', (integer) $metricsAttrs->statements);
                    $class->setMetric('php_code_coverage.covered_statements', (integer) $metricsAttrs->coveredstatements);
                    $class->setMetric('php_code_coverage.elements', (integer) $metricsAttrs->elements);
                    $class->setMetric('php_code_coverage.covered_elements', (integer) $metricsAttrs->coveredelements);

                    $i = -1;
                    $addedClassMethods = 0;
                    foreach ($fileNode->xpath('./line') as $lineNode) {
                        $lineAttrs = $lineNode->attributes();

                        if ((string) $lineAttrs->type !== 'method') {
                            continue;
                        }
                        $i += 1;

                        if ($i < $addedMethods) {
                            continue;
                        }

                        if ($addedClassMethods >= (integer) $metricsAttrs->methods) {
                            break;
                        }

                        $addedClassMethods += 1;
                        $addedMethods += 1;
                        $method = $project->getOrCreateCodeElement('method', $className.'::'.$lineAttrs->name);
                        $class->addChild($method);

                        $method->setMetric('php_code_coverage.change_risk_anti_pattern', (integer) $lineAttrs->crap);
                        $method->setMetric('php_code_coverage.count', (integer) $lineAttrs->count);
                    }
                }
            }
        }
    }

    private function modifyListenerConfig($phpunitConfig, $rootDir, array $affectedFiles)
    {
        $doc = new \DOMDocument('1.0', 'utf8');
        $doc->loadXml(file_get_contents($phpunitConfig));
        $xpath = new \DOMXPath($doc);

        $phpunitElemList = $xpath->query('//phpunit');
        if ($phpunitElemList->length === 0) {
            throw new \LogicException(sprintf('The PHPUnit config file "%s" has no "<phpunit>" element.', $phpunitConfig));
        }
        $phpunitElem = $phpunitElemList->item(0);

        $listenersList = $xpath->query('//phpunit/listeners');
        if (0 === $listenersList->length) {
            $listenersElem = $doc->createElement('listeners');
            $phpunitElem->appendChild($listenersElem);
        } else {
            $listenersElem = $listenersList->item(0);
        }

        $listener = $doc->createElement('listener');
        $listener->setAttribute('class', 'TestSkippingListener');
        $listener->setAttribute('file', __DIR__.'/Util/TestSkippingListener.php');
        $listenersElem->appendChild($listener);

        $args = $doc->createElement('arguments');
        $listener->appendChild($args);

        $rootDir = $doc->createElement('string', $rootDir);
        $args->appendChild($rootDir);

        $paths = $doc->createElement('array');
        $args->appendChild($paths);

        foreach ($affectedFiles as $i => $pathname) {
            $path = $doc->createElement('element');
            $path->setAttribute('key', $i);

            // There is a bug in PHPUnit which counts the element starting from 1 instead of 0.
            $path->appendChild($doc->createElement('dummy'));

            $strValue = $doc->createElement('string', $pathname);
            $path->appendChild($strValue);
            $paths->appendChild($path);
        }

        file_put_contents($phpunitConfig, $doc->saveXML());
    }

    private function findPhpUnitConfig(Project $project)
    {
        $dir = $project->getDir();
        $configPath = $project->getGlobalConfig('config_path');

        if (null !== $configPath) {
            if ( ! is_file($dir.'/'.$configPath)) {
                throw new \LogicException(sprintf('The config file "%s" does not exist.', $dir.'/'.$configPath));
            }

            return $dir.'/'.$configPath;
        } elseif (is_file($dir.'/phpunit.xml')) {
            return $dir.'/phpunit.xml';
        } elseif (is_file($dir.'/phpunit.xml.dist')) {
            return $dir.'/phpunit.xml.dist';
        } else {
            return null;
        }
    }
}