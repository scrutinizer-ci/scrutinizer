<?php

namespace Scrutinizer;

use Scrutinizer\Analyzer\Javascript\JsLintAnalyzer;
use Scrutinizer\Config\ConfigBuilder;
use Scrutinizer\Model\File;
use Scrutinizer\Model\Project;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Yaml\Yaml;

class Scrutinizer
{
    private $analyzers = array();

    public function __construct()
    {
        $this->registerAnalyzer(new JsLintAnalyzer());
    }

    public function registerAnalyzer($analyzer)
    {
        $this->analyzers[] = $analyzer;
    }

    public function getConfiguration()
    {
        return new Configuration($this->analyzers);
    }

    public function scrutinize($dir, array $rawConfig = array())
    {
        if ( ! is_dir($dir)) {
            throw new \InvalidArgumentException(sprintf('The directory "%s" does not exist.', $dir));
        }
        $dirLength = strlen(realpath($dir));

        if ( ! $rawConfig && is_file($dir.'/.scrutinizer.yml')) {
            $rawConfig = Yaml::parse(file_get_contents($dir.'/.scrutinizer.yml'));
        }
        $config = $this->getConfiguration()->process($rawConfig);

        $matches = function(array $patterns, $path) {
            foreach ($patterns as $pattern) {
                if (fnmatch($pattern, $path)) {
                    return true;
                }
            }

            return false;
        };

        $files = array();
        foreach (Finder::create()->files()->in($dir) as $file) {
            $relPath = substr($file->getRealPath(), $dirLength + 1);

            if ($config['filter']['paths'] && ! $matches($config['filter']['paths'], $relPath)) {
                continue;
            }

            if ($config['filter']['excluded_paths'] && $matches($config['filter']['excluded_paths'], $relPath)) {
                continue;
            }

            $files[] = new File($relPath, file_get_contents($file->getRealPath()));
        }

        $project = new Project($files, $config);
        foreach ($this->analyzers as $analyzer) {
            $analyzer->scrutinize($project);
        }

        return $project;
    }
}