<?php

namespace Scrutinizer\Tests\Analyzer\Php\Util;

use Scrutinizer\Analyzer\Php\Util\ImpactAnalyzer;

class ImpactAnalyzerTest extends \PHPUnit_Framework_TestCase
{
    /** @var ImpactAnalyzer */
    private $analyzer;

    public function testAliasedClassIsModified()
    {
        $this->assertEquals(
            array(
                'FooTestFixture.php',
            ),
            $this->analyze('ClassWithTests', array('Foo.php'))
        );
    }

    public function testMockedClass()
    {
        $this->assertEquals(
            array(
                'Foo.php',
                'FooTestFixture.php',
            ),
            $this->analyze('ClassWithTests', array('Bar.php'))
        );
    }

    protected function setUp()
    {
        $this->analyzer = new ImpactAnalyzer();
    }

    private function analyze($subDir, array $changedPaths)
    {
        $dir = __DIR__.'/Fixture/'.$subDir;
        if ( ! is_dir($dir)) {
            throw new \LogicException(sprintf('The directory "%s" does not exist.', $dir));
        }

        return $this->analyzer->findAffectedFiles($dir, $changedPaths);
    }
}