<?php

namespace Scrutinizer\Tests\Cache;

use PhpOption\None;
use PhpOption\Some;
use Scrutinizer\Analyzer\AnalyzerInterface;
use Scrutinizer\Cache\AnalyzerScopedCache;
use Scrutinizer\Model\File;

class AnalyzerScopedCacheTest extends \PHPUnit_Framework_TestCase
{
    private $delegate;

    public function testGet()
    {
        $file = new File('Foo.php', 'abc');
        $this->delegate->expects($this->at(0))
            ->method('get')
            ->with($file, 'bar.97d170.foo')
            ->will($this->returnValue(None::create()));
        $this->delegate->expects($this->at(1))
            ->method('get')
            ->with($file, 'baz.a5e744.sdf')
            ->will($this->returnValue(new Some('abc')));

        $cache = $this->createCache($this->createAnalyzer('bar'), array());
        $this->assertEquals(None::create(), $cache->get($file, 'foo'));

        $cache = $this->createCache($this->createAnalyzer('baz'), array('foo' => 'bar'));
        $this->assertEquals(new Some('abc'), $cache->get($file, 'sdf'));
    }

    public function testStore()
    {
        $file = new File('Foo.php', 'abc');

        $this->delegate->expects($this->once())
            ->method('store')
            ->with($file, 'bar.97d170.foo', 'abcdef');

        $cache = $this->createCache($this->createAnalyzer('bar'), array());
        $cache->store($file, 'foo', 'abcdef');
    }

    public function testWithCache()
    {
        $file = new File('Foo.php', 'abc');
        $this->delegate->expects($this->once())
            ->method('withCache')
            ->with($file, 'bar.97d170.foo', $this->isType('callable'), $this->isType('callable'));

        $cache = $this->createCache($this->createAnalyzer('bar'), array());
        $cache->withCache($file, 'foo', function() { }, function() { });
    }

    private function createCache(AnalyzerInterface $analyzer, array $config)
    {
        return new AnalyzerScopedCache($this->delegate, $analyzer, $config);
    }

    private function createAnalyzer($name)
    {
        $analyzer = $this->getMock('Scrutinizer\Analyzer\AnalyzerInterface');
        $analyzer->expects($this->any())
            ->method('getName')
            ->will($this->returnValue($name));

        return $analyzer;
    }

    protected function setUp()
    {
        $this->delegate = $this->getMock('Scrutinizer\Cache\FileCacheInterface');
    }
}