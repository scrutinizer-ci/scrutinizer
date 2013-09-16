<?php

namespace Scrutinizer;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Scrutinizer\Analyzer\AnalyzerInterface;
use Scrutinizer\Analyzer\LoggerAwareInterface;
use Scrutinizer\Analyzer;
use Scrutinizer\Event\ProjectEvent;
use Scrutinizer\Logger\LoggableProcess;
use Scrutinizer\Model\Project;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Process\Exception\RuntimeException;
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
    const REVISION = '@revision@';

    const EVENT_POST_ANALYSIS = 'post_analysis';

    private $logger;
    private $analyzers = array();
    private $dispatcher;

    public function __construct(LoggerInterface $logger = null)
    {
        $this->logger = $logger ?: new NullLogger();
        $this->dispatcher = new EventDispatcher();

        $this->registerAnalyzer(new Analyzer\Javascript\JsHintAnalyzer());
        $this->registerAnalyzer(new Analyzer\Php\MessDetectorAnalyzer());
        $this->registerAnalyzer(new Analyzer\Php\CsFixerAnalyzer());
        $this->registerAnalyzer(new Analyzer\Php\PhpAnalyzer());
        $this->registerAnalyzer(new Analyzer\Php\CsAnalyzer());
        $this->registerAnalyzer(new Analyzer\Php\SecurityAdvisoryAnalyzer());
        $this->registerAnalyzer(new Analyzer\Php\CodeCoverageAnalyzer());
        $this->registerAnalyzer(new Analyzer\Php\CopyPasteDetectorAnalyzer());
        $this->registerAnalyzer(new Analyzer\Php\LocAnalyzer());
        $this->registerAnalyzer(new Analyzer\Php\PDependAnalyzer());
        $this->registerAnalyzer(new Analyzer\ExternalCodeCoverageAnalyzer());
        $this->registerAnalyzer(new Analyzer\CustomAnalyzer());

        $this->registerSubscriber(new Event\Php\LocationCompletionSubscriber());
    }

    public function registerSubscriber(EventSubscriberInterface $subscriber)
    {
        $this->dispatcher->addSubscriber($subscriber);
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

        if ( ! empty($config['before_commands'])) {
            $this->logger->info('Executing before commands'."\n");
            foreach ($config['before_commands'] as $cmd) {
                $this->logger->info(sprintf('Running "%s"...'."\n", $cmd));
                $proc = new LoggableProcess($cmd, $dir);
                $proc->setTimeout(900);
                $proc->setIdleTimeout(300);
                $proc->setPty(true);
                $proc->setLogger($this->logger);
                $proc->run();
            }
        }

        $project = new Project($dir, $config, $paths);
        foreach ($this->analyzers as $analyzer) {
            if ( ! $project->isAnalyzerEnabled($analyzer->getName())) {
                continue;
            }

            $this->logger->info(sprintf('Running analyzer "%s"...'."\n", $analyzer->getName()));
            $project->setAnalyzerName($analyzer->getName());
            $analyzer->scrutinize($project);
        }

        $this->dispatcher->dispatch(self::EVENT_POST_ANALYSIS, new ProjectEvent($project));

        if ( ! empty($config['after_commands'])) {
            $this->logger->info('Executing after commands'."\n");
            foreach ($config['after_commands'] as $cmd) {
                $this->logger->info(sprintf('Running "%s"...'."\n", $cmd));
                $proc = new LoggableProcess($cmd, $dir);
                $proc->setTimeout(900);
                $proc->setIdleTimeout(300);
                $proc->setPty(true);
                $proc->setLogger($this->logger);
                $proc->run();
            }
        }

        return $project;
    }
}