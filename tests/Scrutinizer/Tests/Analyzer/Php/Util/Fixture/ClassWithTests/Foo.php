<?php

namespace Scrutinizer\Tests\Worker\Php\Fixture\ClassWithTests;

class Foo
{
    private $bar;

    public function __construct(Bar $bar)
    {
        $this->bar = $bar;
    }

    public function getBar()
    {
        return $this->bar;
    }
}