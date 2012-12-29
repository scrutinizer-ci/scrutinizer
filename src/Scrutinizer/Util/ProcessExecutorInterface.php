<?php

namespace Scrutinizer\Util;

use Symfony\Component\Process\Process;

interface ProcessExecutorInterface
{
    /**
     * Executes the given process, and returns the executed process.
     * 
     * @param Process $proc
     * 
     * @return ProcessResult the result of the executed process
     */
    function execute(Process $proc);
}
