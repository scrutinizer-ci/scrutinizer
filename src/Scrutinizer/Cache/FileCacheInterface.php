<?php

namespace Scrutinizer\Cache;

use PhpOption\Option;
use Scrutinizer\Analyzer\AnalyzerInterface;
use Scrutinizer\Model\File;

interface FileCacheInterface
{
    /**
     * @param string $key
     * @return Option
     */
    public function get(File $file, $key);

    /**
     * @param string $key
     * @param string $value
     *
     * @return void
     */
    public function store(File $file, $key, $value);

    /**
     * @param string $key
     * @return void
     */
    public function withCache(File $file, $key, callable $generator, callable $successCallback);
}