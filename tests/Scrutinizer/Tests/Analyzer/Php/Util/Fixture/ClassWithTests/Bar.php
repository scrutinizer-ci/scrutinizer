<?php

namespace Scrutinizer\Tests\Worker\Php\Fixture\ClassWithTests;

class Bar
{
    private $name;

    public function __construct($name)
    {
        $this->name = $name;
    }

    public function getName()
    {
        return $this->name;
    }
}