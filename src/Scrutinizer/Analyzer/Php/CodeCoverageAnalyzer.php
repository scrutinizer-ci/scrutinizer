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

        if ($proc->getExitCode() > 1) {
            throw new ProcessFailedException($proc);
        }

        if (empty($output)) {
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

    public function getMetrics()
    {
        return array();
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