<?php

namespace Scrutinizer\Model;

use PhpOption\Some;
use PhpOption\None;
use Symfony\Component\Finder\Finder;
use Scrutinizer\Util\PathUtils;

class Project implements ProjectInterface
{
    private $files;
    private $config;

    private $analyzerName;

    public static function createFromDirectory($dir, array $config)
    {
        $dirLength = strlen($dir);
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

        return new self($files, $config);
    }

    public static function createFromFiles(array $files, array $config)
    {
    }

    public function __construct(array $files, array $config)
    {
        $this->files = $files;
        $this->config = $config;
    }

    public function getFile($path)
    {
        if ( ! isset($this->files[$path])) {
            return None::create();
        }

        return new Some($this->files[$path]);
    }

    /**
     * @param string $name
     */
    public function setAnalyzerName($name)
    {
        if ( ! isset($this->config[$name])) {
            throw new \InvalidArgumentException(sprintf('The analyzer "%s" does not exist.', $name));
        }

        $this->analyzerName = $name;
    }

    /**
     * Returns a path specific configuration setting, or the default if there
     * is no path-specific configuration for the file.
     *
     * @param File $file
     * @param string $configPath
     * @param string $default
     *
     * @return mixed
     */
    public function getPathConfig(File $file, $configPath, $default = null)
    {
        $segments = explode('.', $configPath);

        if ( ! isset($this->config[$this->analyzerName]['path_configs'])) {
            return $default;
        }

        $filePath = $file->getPath();
        foreach ($this->config[$this->analyzerName]['path_configs'] as $pathConfig) {
            if ( ! PathUtils::matches($filePath, $pathConfig['paths'])) {
                continue;
            }

            return $this->walkConfig($pathConfig['paths'], $segments, $configPath);
        }

        return $default;
    }

    /**
     * Returns a file-specific configuration setting.
     *
     * This method first looks whether there is a path-specific setting, and if not
     * falls back to fetch the value from the default configuration.
     *
     * @param File $file
     * @param string $configPath
     *
     * @return mixed
     */
    public function getFileConfig(File $file, $configPath)
    {
        $segments = explode('.', $configPath);

        if ( ! isset($this->config[$this->analyzerName]['config'])) {
            throw new \InvalidArgumentException(sprintf('The analyzer "%s" has no per-file configuration. Did you want to use getGlobalConfig() instead?', $this->analyzerName));
        }

        $pathConfig = null;
        $relPath = $this->analyzerName.'.config';
        if (isset($this->config[$this->analyzerName]['path_configs'])) {
            $filePath = $file->getPath();
            foreach ($this->config[$this->analyzerName]['path_configs'] as $k => $pathConfig) {
                if ( ! PathUtils::matches($filePath, $pathConfig['paths'])) {
                    continue;
                }

                $relPath = $this->analyzerName.'.path_configs.'.$k.'.config';
                $config = $pathConfig['config'];
                break;
            }
        }

        return $this->walkConfig($pathConfig ?: $this->config[$this->analyzerName]['config'], $segments, $relPath);
    }

    /**
     * Returns a global configuration setting.
     *
     * @param string $configPath
     *
     * @return mixed
     */
    public function getGlobalConfig($configPath)
    {
        $segments = explode('.', $configPath);

        return $this->walkConfig($this->config[$this->analyzerName], $segments, $this->analyzerName);
    }

    public function getFiles()
    {
        return $this->files;
    }

    private function matches(array $patterns, $path)
    {
        foreach ($patterns as $pattern) {
            if (fnmatch($pattern, $path)) {
                return true;
            }
        }

        return false;
    }

    private function walkConfig($config, array $segments, $relPath = null)
    {
        $fullPath = ($relPath ? $relPath.'.' : '').implode('.', $segments);

        $walked = $relPath;
        foreach ($segments as $segment) {
            $walked .= $walked ? '.'.$segment : $segment;

            if ( ! array_key_exists($segment, $config)) {
                throw new \InvalidArgumentException(sprintf('There is no config at path "%s"; walked path: "%s".', $fullPath, $walked));
            }

            $config = $config[$segment];
        }

        return $config;
    }
}