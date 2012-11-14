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
     * @return Process the executed process (might be different from passed process)
     */
    function execute(Process $proc);
}
