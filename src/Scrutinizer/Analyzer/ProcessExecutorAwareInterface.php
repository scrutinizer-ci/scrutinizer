<?php

namespace Scrutinizer\Analyzer;

use Scrutinizer\Util\ProcessExecutorInterface;

interface ProcessExecutorAwareInterface
{
    /**
     * @param \Scrutinizer\Util\ProcessExecutorInterface $executor
     * 
     * @return void
     */
    function setProcessExecutor(ProcessExecutorInterface $executor);
}
