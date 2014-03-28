<?php

namespace Scrutinizer\Cache;

use PhpOption\None;
use PhpOption\Some;
use Scrutinizer\Analyzer\AnalyzerInterface;
use Scrutinizer\Model\File;

class FilesystemCache implements FileCacheInterface
{
    private $dir;

    public function __construct($dir)
    {
        $this->dir = $dir;
    }

    public function get(File $file, $key)
    {
        $cacheFile = $this->getCacheFile($file, $key);
        if ( ! is_file($cacheFile)) {
            return None::create();
        }

        return new Some(file_get_contents($cacheFile));
    }

    public function store(File $file, $key, $value)
    {
        $cacheFile = $this->getCacheFile($file, $key);
        $dir = dirname($cacheFile);
        if ( ! is_dir($dir)) {
            if (false === @mkdir($dir, 0777, true)) {
                throw new \RuntimeException(sprintf('Could not create "%s".', $dir));
            }
        }

        file_put_contents($cacheFile, $value);
    }

    public function withCache(File $file, $key, callable $generator, callable $callback)
    {
        $content = $this->get($file, $key)
            ->getOrCall(function() use ($file, $key, $generator) {
                $content = $generator();
                $this->store($file, $key, $content);

                return $content;
            })
        ;

        $callback($content);
    }

    private function getCacheFile(File $file, $key)
    {
        return $this->dir.'/'.substr($file->getHash(), 0, 2).'/'.substr($file->getHash(), 2).'/'.sha1($key);
    }
}