<?php

namespace Scrutinizer\Cache;

use Scrutinizer\Analyzer\AnalyzerInterface;
use Scrutinizer\Model\File;

class AnalyzerScopedCache implements FileCacheInterface
{
    private $delegate;
    private $analyzerName;
    private $configHash;

    public function __construct(FileCacheInterface $delegate, AnalyzerInterface $analyzer, array $config)
    {
        $this->delegate = $delegate;
        $this->analyzerName = $analyzer->getName();
        $this->configHash = substr(sha1(json_encode($config)), 0, 6);
    }

    public function get(File $file, $key)
    {
        return $this->delegate->get($file, $this->generateKey($key));
    }

    public function store(File $file, $key, $content)
    {
        $this->delegate->store($file, $this->generateKey($key), $content);
    }

    public function withCache(File $file, $key, callable $generator, callable $block)
    {
        $this->delegate->withCache($file, $this->generateKey($key), $generator, $block);
    }

    private function generateKey($key)
    {
        return $this->analyzerName.'.'.$this->configHash.'.'.$key;
    }
}