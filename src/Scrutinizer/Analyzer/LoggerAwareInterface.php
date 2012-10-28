<?php

namespace Scrutinizer\Analyzer;

use Monolog\Logger;

interface LoggerAwareInterface
{
    /**
     * Sets the logger.
     *
     * @param Logger $logger
     *
     * @return void
     */
    function setLogger(Logger $logger);
}