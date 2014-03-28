<?php

namespace Scrutinizer\Cache;

use PhpOption\None;
use Scrutinizer\Model\File;

class NullCache implements FileCacheInterface
{
    public function get(File $file, $key)
    {
        return None::create();
    }

    public function store(File $file, $key, $content)
    {
    }

    public function withCache(File $file, $key, callable $generator, callable $block)
    {
        $block($generator());
    }
}