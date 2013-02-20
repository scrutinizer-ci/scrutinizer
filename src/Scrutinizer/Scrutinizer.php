<?php

namespace Scrutinizer;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Scrutinizer\Analyzer\AnalyzerInterface;
use Scrutinizer\Analyzer\LoggerAwareInterface;
use Scrutinizer\Analyzer;
use Scrutinizer\Logger\LoggableProcess;
use Scrutinizer\Model\Project;
use Symfony\Component\Yaml\Yaml;

/**
 * The Scrutinizer.
 *
 * Ties together analyzers, and can be used to easily scrutinize a project directory.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class Scrutinizer
{
    private $logger;
    private $analyzers = array();

    public function __construct(LoggerInterface $logger = null)
    {
        if (null === $logger) {
            $logger = new NullLogger();
        }
        $this->logger = $logger;

        $this->registerAnalyzer(new Analyzer\Javascript\JsHintAnalyzer());
        $this->registerAnalyzer(new Analyzer\Php\MessDetectorAnalyzer());
        $this->registerAnalyzer(new Analyzer\Php\CsFixerAnalyzer());
        $this->registerAnalyzer(new Analyzer\Php\PhpAnalyzer());
        $this->registerAnalyzer(new Analyzer\Php\CsAnalyzer());
        $this->registerAnalyzer(new Analyzer\Php\SecurityAdvisoryAnalyzer());
        $this->registerAnalyzer(new Analyzer\CustomAnalyzer());
    }

    public function registerAnalyzer(AnalyzerInterface $analyzer)
    {
        if ($analyzer instanceof \Psr\Log\LoggerAwareInterface) {
            $analyzer->setLogger($this->logger);
        }

        $this->analyzers[] = $analyzer;
    }

    public function getAnalyzers()
    {
        return $this->analyzers;
    }

    public function getConfiguration()
    {
        return new Configuration($this->analyzers);
    }

    public function scrutinize($dir, array $paths = array())
    {
        if ( ! is_dir($dir)) {
            throw new \InvalidArgumentException(sprintf('The directory "%s" does not exist.', $dir));
        }
        $dir = realpath($dir);

        $rawConfig = array();
        if (is_file($dir.'/.scrutinizer.yml')) {
            $rawConfig = Yaml::parse(file_get_contents($dir.'/.scrutinizer.yml')) ?: array();
        }

        $config = $this->getConfiguration()->process($rawConfig);

        foreach ($config['before_commands'] as $cmd) {
            $proc = new LoggableProcess($cmd, $dir);
            $proc->setLogger($this->logger);
            $proc->run();
        }

        $project = new Project($dir, $config, $paths);
        foreach ($this->analyzers as $analyzer) {
            $project->setAnalyzerName($analyzer->getName());
            $analyzer->scrutinize($project);
        }

        foreach ($config['after_commands'] as $cmd) {
            $proc = new LoggableProcess($cmd, $dir);
            $proc->setLogger($this->logger);
            $proc->run();
        }

        return $project;
    }
}