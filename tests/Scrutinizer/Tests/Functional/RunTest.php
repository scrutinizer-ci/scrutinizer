<?php

namespace Scrutinizer\Tests\Functional;

use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class RunTest extends \PHPUnit_Framework_TestCase
{
    public function testProfiling()
    {
        $outputFile = tempnam(sys_get_temp_dir(), 'profile');
        $proc = $this->runCmd('run', array(__DIR__.'/Fixture/JsProject', '--profiler-output-file='.$outputFile));
        $profilerOutput = json_decode(file_get_contents($outputFile), true);
        unlink($outputFile);

        $this->assertInternalType('array', $profilerOutput);
        $this->assertEquals(
            array(
                'start', 'pass.js_hint.start', 'pass.js_hint.end', 'pass.custom_commands.start', 'pass.custom_commands.end',
                'output.start', 'output.end', 'stop'
            ),
            array_keys($profilerOutput)
        );
    }

    public function testRun()
    {
        $proc = $this->runCmd('run', array(__DIR__.'/Fixture/JsProject'));

        $this->assertSame(0, $proc->getExitCode(), $proc->getOutput().$proc->getErrorOutput());

        $expectedOutputArray = array(
          "Running analyzer \"js_hint\"...",
          ".\n",
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

    public function testRunWithJsHintConfigError()
    {
        $proc = $this->runCmd('run', array(__DIR__.'/Fixture/JsProjectWithJsHintConfigError'));

        $expectedOutput = <<<OUTPUT
Running analyzer "js_hint"...
.

Errors:
 - JSHint config error when analyzing "some_file.js": Bad option: 'camelCase'.

Scanned Files: 2, Comments: 0

OUTPUT;

        $this->assertEquals($expectedOutput, $proc->getOutput());
    }

    private function runCmd($command, array $args = array())
    {
        $proc = new Process('php '.__DIR__.'/../../../../bin/scrutinizer '.escapeshellarg($command).' '.implode(" ", array_map('escapeshellarg', $args)));
        $proc->run();

        return $proc;
    }
}
