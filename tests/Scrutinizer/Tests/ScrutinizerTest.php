<?php

namespace Scrutinizer\Tests;

use Scrutinizer\Scrutinizer;

class ScrutinizerTest extends \PHPUnit_Framework_TestCase
{
    public function testConfiguration()
    {
        $config = (new Scrutinizer())->getConfiguration();

        $rs = $config->process(array('tools' => array('php_mess_detector' => true)));
        $this->assertTrue($rs['tools']['php_mess_detector']['enabled']);
        $this->assertFalse($rs['tools']['php_cs_fixer']['enabled']);
    }
}