<?php

namespace Scrutinizer;

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
    private $analyzers = array();

    public function __construct()
    {
        $this->registerAnalyzer(new JsHintAnalyzer());
    }

    public function registerAnalyzer($analyzer)
    {
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

        $project = new Project($files, $config);
        foreach ($this->analyzers as $analyzer) {
            $analyzer->scrutinize($project);
        }

        return $project;
    }

    public function scrutinizeFiles(array $files, array $rawConfig = array())
    {
        $config = $this->getConfiguration()->process($rawConfig);

        $project = new Project($files, $config);
        foreach ($this->analyzers as $analyzer) {
            $analyzer->scrutinize($project);
        }

        return $config;
    }
}