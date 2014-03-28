<?php

namespace Scrutinizer\Cache;

interface CacheAwareInterface
{
    /**
     * @return void
     */
    public function setCache(FileCacheInterface $cache);
}