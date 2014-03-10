<?php

namespace Scrutinizer\Tests\Functional;

use Scrutinizer\Model\Location;
use Scrutinizer\Scrutinizer;

class PhpElementLocationTest extends \PHPUnit_Framework_TestCase
{
    public function testAddsElementLocations()
    {
        $project = (new Scrutinizer())->scrutinize(__DIR__.'/Fixture/PhpProject/');

        $this->assertEquals(
            new Location('NiceClass.php', 3, 9),
            $project->getCodeElements()[1]->getLocation()
        );
        $this->assertEquals(
            new Location('NiceClass.php', 5, 8),
            $project->getCodeElements()[2]->getLocation()
        );
    }
}