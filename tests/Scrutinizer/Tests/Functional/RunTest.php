<?php

namespace Scrutinizer\Tests\Functional;

use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class RunTest extends \PHPUnit_Framework_TestCase
{
    public function testRun()
    {
        $proc = $this->runCmd('run', array(__DIR__.'/Fixture/JsProject'));

        $this->assertSame(0, $proc->getExitCode());
        $this->assertEquals(<<<'TEXT'
some_file.js
============
Line 1: Unused variable: 'foo'
Line 2: Unused variable: 'x'

Scanned Files: 1, Comments: 2

TEXT
            , $proc->getOutput());
    }

    private function runCmd($command, array $args = array())
    {
        $proc = new Process(__DIR__.'/../../../../bin/scrutinizer '.escapeshellarg($command).' '.implode(" ", array_map('escapeshellarg', $args)));
        $proc->run();

        return $proc;
    }
}