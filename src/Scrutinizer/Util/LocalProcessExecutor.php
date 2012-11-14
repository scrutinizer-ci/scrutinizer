<?php

namespace Scrutinizer\Util;

use Symfony\Component\Process\Process;

class LocalProcessExecutor implements ProcessExecutorInterface
{
    public function execute(Process $proc)
    {
        $proc->run();
        
        return $proc;
    }
}