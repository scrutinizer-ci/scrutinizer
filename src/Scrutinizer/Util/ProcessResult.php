<?php

namespace Scrutinizer\Util;

class ProcessResult
{
    private $exitCode;
    private $output;
    private $errorOutput;

    public function __construct($exitCode, $output, $errorOutput)
    {
        $this->exitCode = $exitCode;
        $this->output = $output;
        $this->errorOutput = $errorOutput;
    }
}