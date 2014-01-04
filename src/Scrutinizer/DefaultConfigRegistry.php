<?php

namespace Scrutinizer;

use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

class DefaultConfigRegistry
{
    private $configDir;

    public function __construct($configDir = null)
    {
        $this->configDir = $configDir ?: __DIR__.'/../../res/default-configs';

        if ( ! is_dir($this->configDir)) {
            throw new \InvalidArgumentException(sprintf('The config directory "%s" does not exist.', $this->configDir));
        }
    }

    public function getConfig($name)
    {
        if ( ! is_file($this->configDir.'/'.$name.'.yml')) {
            throw new \RuntimeException(sprintf('The config "%s.yml" does not exist in "%s".', $name, $this->configDir));
        }

        return file_get_contents($this->configDir.'/'.$name.'.yml');
    }

    public function getAvailableConfigs()
    {
        $files = array();
        foreach (Finder::create()->in($this->configDir)->files()->name('*.yml') as $file) {
            /** @var SplFileInfo $file */
            $files[] = $file->getBasename('.yml');
        }

        return $files;
    }
}