<?php

namespace Scrutinizer\Tests\Functional;

use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class RunTest extends \PHPUnit_Framework_TestCase
{
    public function testRun()
    {
        $proc = $this->runCmd('run', array(__DIR__.'/Fixture/JsProject'));

        $this->assertSame(0, $proc->getExitCode(), $proc->getOutput().$proc->getErrorOutput());

        $expectedOutput = "Running analyzer \"js_hint\"...\n\n\r    Files 1/1 [............................................................] 100%\n\nRunning analyzer \"custom_commands\"...\nsome_file.js\n============\nLine 1: Unused variable: 'foo'\nLine 2: Unused variable: 'x'\n\nScanned Files: 1, Comments: 2\n";
        $this->assertEquals($expectedOutput, $proc->getOutput());
    }

    private function runCmd($command, array $args = array())
    {
        $proc = new Process('php '.__DIR__.'/../../../../bin/scrutinizer '.escapeshellarg($command).' '.implode(" ", array_map('escapeshellarg', $args)));
        $proc->run();

        return $proc;
    }
}