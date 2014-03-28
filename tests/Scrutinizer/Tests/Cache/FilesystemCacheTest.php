<?php

namespace Scrutinizer\Tests\Cache;

use PhpOption\None;
use PhpOption\Some;
use Scrutinizer\Cache\FilesystemCache;
use Scrutinizer\Model\File;
use Symfony\Component\Filesystem\Filesystem;

class FilesystemCacheTest extends \PHPUnit_Framework_TestCase
{
    /** @var FilesystemCache */
    private $cache;

    private $cacheDir;

    public function testCache()
    {
        $file = new File('Foo.php', 'abc');

        $this->assertEquals(None::create(), $this->cache->get($file, 'foo'));

        $this->cache->store($file, 'foo', 'bar');
        $this->assertEquals(new Some('bar'), $this->cache->get($file, 'foo'));
        $this->assertEquals(None::create(), $this->cache->get($file, 'bar'));

        $file = new File('Foo.php', 'def');
        $this->assertEquals(None::create(), $this->cache->get($file, 'foo'));

        $file = new File('Bar.php', 'abc');
        $this->assertEquals(None::create(), $this->cache->get($file, 'foo'));
    }

    public function testWithCache()
    {
        $file = new File('Foo.php', 'abc');
        $this->cache->withCache($file, 'foo', function() { return 'bar'; }, function($result) {
            $this->assertEquals('bar', $result);
        });

        $this->cache->withCache($file, 'foo', function() { $this->fail('Should be cached.'); }, function($result) {
            $this->assertEquals('bar', $result);
        });
    }

    protected function setUp()
    {
        $this->cacheDir = tempnam(sys_get_temp_dir(), 'filesystem-cache');
        unlink($this->cacheDir);
        mkdir($this->cacheDir, 0777, true);

        $this->cache = new FilesystemCache($this->cacheDir);
    }

    protected function tearDown()
    {
        $fs = new Filesystem();
        $fs->remove($this->cacheDir);
    }
}