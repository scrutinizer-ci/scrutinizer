<?php

namespace Scrutinizer;

use Monolog\Handler\NullHandler;
use Scrutinizer\Analyzer\AnalyzerInterface;
use Scrutinizer\Analyzer\LoggerAwareInterface;
use Monolog\Logger;
use Scrutinizer\Analyzer\PHP\MessDetectorAnalyzer;
use Scrutinizer\Analyzer\Javascript\JsHintAnalyzer;
use Scrutinizer\Util\PathUtils;
use Scrutinizer\Config\ConfigBuilder;
use Scrutinizer\Model\File;
use Scrutinizer\Model\Project;
use Symfony\Component\Finder\Finder;
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

    public function __construct(Logger $logger = null)
    {
        if (null === $logger) {
            $logger = new Logger('scrutinizer');
            $logger->pushHandler(new NullHandler());
        }

        $this->logger = $logger;

        $this->registerAnalyzer(new JsHintAnalyzer());
        $this->registerAnalyzer(new MessDetectorAnalyzer());
    }

    public function registerAnalyzer(AnalyzerInterface $analyzer)
    {
        if ($analyzer instanceof LoggerAwareInterface) {
            $analyzer->setLogger($this->logger);
        }

        $this->analyzers[] = $analyzer;
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

        $files = array();
        foreach (Finder::create()->files()->in($dir) as $file) {
            $relPath = substr($file->getRealPath(), $dirLength + 1);

            if ($config['filter']['paths'] && ! PathUtils::matches($relPath, $config['filter']['paths'])) {
                continue;
            }

            if ($config['filter']['excluded_paths'] && PathUtils::matches($relPath, $config['filter']['excluded_paths'])) {
                continue;
            }

            $files[$relPath] = new File($relPath, file_get_contents($file->getRealPath()));
        }
        $this->logger->info(sprintf('Found %d files in directory.', count($files)));

        $project = new Project($files, $config);
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
            $this->logger->info(sprintf('Running analyzer "%s".', $analyzer->getName()), array('analyzer' => $analyzer));
            try {
                $analyzer->scrutinize($project);
            } catch (\Exception $ex) {
                $this->logger->err(sprintf('An error occurred in analyzer "%s": %s', $analyzer->getName(), $ex->getMessage()), array('analyzer' => $analyzer, 'exception' => $ex));
            }
        }
    }
}