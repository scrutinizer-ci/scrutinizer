<?php

class TestSkippingListener implements \PHPUnit_Framework_TestListener
{
    private $baseDir;
    private $affectedPaths;

    public function __construct($baseDir, array $affectedPaths)
    {
        $this->baseDir = $baseDir;
        $this->affectedPaths = $affectedPaths;
    }

    public function addError(\PHPUnit_Framework_Test $test, \Exception $e, $time)
    {
    }

    public function addFailure(\PHPUnit_Framework_Test $test, \PHPUnit_Framework_AssertionFailedError $e, $time)
    {
    }

    public function addIncompleteTest(\PHPUnit_Framework_Test $test, \Exception $e, $time)
    {
    }

    public function addSkippedTest(\PHPUnit_Framework_Test $test, \Exception $e, $time)
    {
    }

    public function startTest(\PHPUnit_Framework_Test $test)
    {
    }

    public function endTest(\PHPUnit_Framework_Test $test, $time)
    {
    }

    public function startTestSuite(\PHPUnit_Framework_TestSuite $suite)
    {
        $this->filterTests($suite);
    }

    public function endTestSuite(\PHPUnit_Framework_TestSuite $suite)
    {
    }

    private function filterTests(\PHPUnit_Framework_TestSuite $suite)
    {
        $ref = new \ReflectionProperty($suite, 'tests');
        $ref->setAccessible(true);

        $newTests = array();
        foreach ($ref->getValue($suite) as $test) {
            if ($test instanceof \PHPUnit_Framework_TestSuite) {
                $this->filterTests($test);
                $newTests[] = $test;
            } elseif ($test instanceof \PHPUnit_Framework_TestCase) {
                if ($this->shouldBeRun($test)) {
                    $newTests[] = $test;
                }
            } else {
                $newTests[] = $test;
            }
        }

        $ref->setValue($suite, $newTests);
    }

    private function shouldBeRun(\PHPUnit_Framework_TestCase $test)
    {
        $ref = new \ReflectionClass($test);
        $pathname = substr($ref->getFileName(), strlen($this->baseDir));

        return in_array($pathname, $this->affectedPaths, true);
    }
}