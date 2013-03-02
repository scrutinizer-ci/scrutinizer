<?php

namespace Scrutinizer\Tests\Worker\Php\Fixture\ClassWithTests;

class FooTestFixture extends \PHPUnit_Framework_TestCase
{
    public function testBar()
    {
        $foo = new Foo(
            $bar = $this->getMock('Scrutinizer\Tests\Worker\Php\Fixture\ClassWithTests\Foo')
        );

        $this->assertSame($bar, $foo->getBar(), 'Foo returns the passed instance.');
    }
}