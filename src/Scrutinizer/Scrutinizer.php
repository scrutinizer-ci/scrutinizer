<?php

namespace Scrutinizer;

use Scrutinizer\Config\ConfigBuilder;
use Scrutinizer\Model\File;
use Scrutinizer\Analyzer\JsLintAnalyzer;

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

    public function getConfig()
    {
        return ConfigBuilder::create('scrutinizer')
            ->children()
                ->arrayNode('filter')
                    ->children()
                        ->arrayNode('paths')
                            ->prototype('scalar')->end()
                        ->end()
                        ->arrayNode('excluded_paths')
                            ->prototype('scalar')->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('default_config')->end()
                ->arrayNode('path_configs')->end()
            ->end()
            ->build()
        ;
    }

    public function scrutinize($dir, array $config)
    {
        if ( ! is_dir($dir)) {
            throw new \InvalidArgumentException(sprintf('The directory "%s" does not exist.', $dir));
        }
        $dirLength = strlen(realpath($dir));

        $paths = isset($config['filter']['paths']) ? (array) $config['filter']['paths'] : array();
        $excludedPaths = isset($config['filter']['excluded_paths']) ? (array) $config['filter']['excluded_paths'] : array();

        $files = array();
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir)) as $file) {
            $relPath = substr($file->getRealPath(), $dirLength + 1);

            if ($paths && ! $this->existsMatch($paths, $relPath)) {
                continue;
            }

            if ($excludedPaths && $this->existsMatch($excludedPath, $relPath)) {
                continue;
            }

            $files[] = new File($relPath, file_get_contents($file->getRealPath()));
        }

        foreach ($this->analyzers as $analyzer) {
            $analyzer->scrutinize($project);
        }

        return $project;
    }

    private function existsMatch(array $patterns, $path)
    {
        foreach ($patterns as $pattern) {
            if (fnmatch($pattern, $path)) {
                return true;
            }
        }

        return false;
    }
}