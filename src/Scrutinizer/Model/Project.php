<?php

namespace Scrutinizer\Model;

use PhpOption\Some;
use PhpOption\None;
use Symfony\Component\Finder\Finder;
use Scrutinizer\Util\PathUtils;
use JMS\Serializer\Annotation as Serializer;


/**
 * @Serializer\ExclusionPolicy("ALL")
 */
class Project
{
    private $dir;

    /** @Serializer\Expose */
    private $config;

    private $paths;
    private $files;
    private $analyzerName;

    public function __construct($dir, array $config, array $paths = array())
    {
        $this->dir = $dir;
        $this->config = $config;
        $this->paths = $paths;
    }

    public function getDir()
    {
        return $this->dir;
    }

    public function getFile($path)
    {
        if (isset($this->files[$path])) {
            return new Some($this->files[$path]);
        }

        if ( ! is_file($this->dir.'/'.$path)) {
            return None::create();
        }

        return new Some($this->files[$path] = new File($path, file_get_contents($this->dir.'/'.$path)));
    }

    public function getFiles()
    {
        return $this->files;
    }

    /**
     * @Serializer\VirtualProperty
     * @Serializer\SerializedName("files")
     */
    public function getFilesWithAnnotations()
    {
        $files = array();
        foreach ($this->files as $file) {
            /** @var $file File */

            if ( ! $file->hasComments() && ! $file->hasProposedPatch() && ! $file->hasMetrics()) {
                continue;
            }

            $files[] = $file;
        }

        return $files;
    }

    /**
     * @param string $name
     */
    public function setAnalyzerName($name)
    {
        if ( ! isset($this->config['tools'][$name])) {
            throw new \InvalidArgumentException(sprintf('The analyzer "%s" does not exist.', $name));
        }

        $this->analyzerName = $name;
    }

    /**
     * Returns a path specific configuration setting, or the default if there
     * is no path-specific configuration for the file.
     *
     * @param string $filePath
     * @param string $configPath
     * @param string $default
     *
     * @return mixed
     */
    public function getPathConfig($filePath, $configPath, $default = null)
    {
        $segments = explode('.', $configPath);

        if ( ! isset($this->config['tools'][$this->analyzerName]['path_configs'])) {
            return $default;
        }

        foreach ($this->config['tools'][$this->analyzerName]['path_configs'] as $pathConfig) {
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
     * @param string|File $filePath
     * @param string $configPath
     *
     * @return mixed
     */
    public function getFileConfig($filePath, $configPath = null)
    {
        if ($filePath instanceof File) {
            $filePath = $filePath->getPath();
        }

        if ( ! isset($this->config['tools'][$this->analyzerName]['config'])) {
            throw new \InvalidArgumentException(sprintf('The analyzer "%s" has no per-file configuration. Did you want to use getGlobalConfig() instead?', $this->analyzerName));
        }

        $segments = explode('.', $configPath);

        $relPath = $this->analyzerName.'.config';
        if (isset($this->config['tools'][$this->analyzerName]['path_configs'])) {
            foreach ($this->config['tools'][$this->analyzerName]['path_configs'] as $k => $pathConfig) {
                if ( ! PathUtils::matches($filePath, $pathConfig['paths'])) {
                    continue;
                }

                $relPath = $this->analyzerName.'.path_configs.'.$k.'.config';

                if (empty($configPath)) {
                    return $pathConfig['config'];
                }

                return $this->walkConfig($pathConfig['config'], $segments, $relPath);
            }
        }

        if (empty($configPath)) {
            return $this->config['tools'][$this->analyzerName]['config'];
        }

        return $this->walkConfig($this->config['tools'][$this->analyzerName]['config'], $segments, $relPath);
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

        return $this->walkConfig($this->config['tools'][$this->analyzerName], $segments, $this->analyzerName);
    }

    public function getAnalyzerConfig()
    {
        return $this->config['tools'][$this->analyzerName];
    }

    public function isAnalyzed($path)
    {
        if (empty($this->paths)) {
            return true;
        }

        return in_array($path, $this->paths, true);
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