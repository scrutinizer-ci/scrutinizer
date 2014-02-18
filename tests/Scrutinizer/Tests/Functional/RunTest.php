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

        $expectedOutputArray = Array (
          "Running analyzer \"js_hint\"...",
          "\n\r    Files 1/1 [............................................................] 100%\n",
          "some_file.js",
          "============",
          "Line 1: 'foo' is defined but never used.",
          //"Line 1: Unused variable: 'foo'",
          "Line 2: 'x' is defined but never used.\n",
          //"Line 2: Unused variable: 'x'\n",
          "Scanned Files: 2, Comments: 2\n"
        );

        $expectedOutput = implode("\n", $expectedOutputArray);
        $this->assertEquals($expectedOutput, $proc->getOutput());
    }

    private function runCmd($command, array $args = array())
    {
        $proc = new Process('php '.__DIR__.'/../../../../bin/scrutinizer '.escapeshellarg($command).' '.implode(" ", array_map('escapeshellarg', $args)));
        $proc->run();

        return $proc;
    }
}
