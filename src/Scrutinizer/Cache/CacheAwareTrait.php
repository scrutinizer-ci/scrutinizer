<?php

namespace Scrutinizer\Cache;

trait CacheAwareTrait
{
    /**
     * @var FileCacheInterface
     */
    protected $cache;

    public function setCache(FileCacheInterface $cache)
    {
        $this->cache = $cache;
    }
}