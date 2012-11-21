<?php

namespace Scrutinizer;

use Monolog\Handler\NullHandler;
use Scrutinizer\Analyzer\AnalyzerInterface;
use Scrutinizer\Analyzer\LoggerAwareInterface;
use Monolog\Logger;
use Scrutinizer\Analyzer\Php\MessDetectorAnalyzer;
use Scrutinizer\Analyzer\Javascript\JsHintAnalyzer;
use Scrutinizer\Model\Project;
use Symfony\Component\Yaml\Yaml;
use Scrutinizer\Util\ProcessExecutorInterface;
use Scrutinizer\Util\FilesystemInterface;

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
    private $processExecutor;
    private $filesystem;
    private $analyzers = array();

    public function __construct(Logger $logger = null, ProcessExecutorInterface $processExecutor = null, FilesystemInterface $filesystem = null)
    {
        if (null === $logger) {
            $logger = new Logger('scrutinizer');
            $logger->pushHandler(new NullHandler());
        }

        $this->logger = $logger;
        $this->processExecutor = $processExecutor ?: new Util\LocalProcessExecutor();
        $this->filesystem = $filesystem ?: new Util\LocalFilesystem();

        $this->registerAnalyzer(new JsHintAnalyzer());
        $this->registerAnalyzer(new MessDetectorAnalyzer());
    }

    public function registerAnalyzer(AnalyzerInterface $analyzer)
    {
        if ($analyzer instanceof LoggerAwareInterface) {
            $analyzer->setLogger($this->logger);
        }
        if ($analyzer instanceof Analyzer\ProcessExecutorAwareInterface) {
            $analyzer->setProcessExecutor($this->processExecutor);
        }
        if ($analyzer instanceof Analyzer\FilesystemAwareInterface) {
            $analyzer->setFilesystem($this->filesystem);
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

    public function scrutinizeDirectory($dir, array $rawConfig = array())
    {
        if ( ! is_dir($dir)) {
            throw new \InvalidArgumentException(sprintf('The directory "%s" does not exist.', $dir));
        }
        $dirLength = strlen(realpath($dir));

        if ( ! $rawConfig && is_file($dir.'/.scrutinizer.yml')) {
            $rawConfig = Yaml::parse(file_get_contents($dir.'/.scrutinizer.yml'));
        }
        $config = $this->getConfiguration()->process($rawConfig);

        $project = Project::createFromDirectory($dir, $config);
        $this->logger->info(sprintf('Found %d files in directory.', count($project->getFiles())));

        $this->scrutinizeProject($project);

        return $project;
    }

    public function scrutinizeFiles(array $files, array $rawConfig = array())
    {
        $config = $this->getConfiguration()->process($rawConfig);
        $project = new Project($files, $config);
        $this->scrutinizeProject($project);

        return $project;
    }

    public function scrutinizeProject(Project $project)
    {
        foreach ($this->analyzers as $analyzer) {
            $project->setAnalyzerName($analyzer->getName());

            $this->logger->info(sprintf('Running analyzer "%s".', $analyzer->getName()), array('analyzer' => $analyzer));
            try {
                $analyzer->scrutinize($project);
            } catch (\Exception $ex) {
                throw $ex;
                $this->logger->err(sprintf('An error occurred in analyzer "%s": %s', $analyzer->getName(), $ex->getMessage()), array('analyzer' => $analyzer, 'exception' => $ex));
            }
        }
    }
}