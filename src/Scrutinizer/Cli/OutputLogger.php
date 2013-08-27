<?php

namespace Scrutinizer\Cli;

use Psr\Log\AbstractLogger;
use Symfony\Component\Console\Output\OutputInterface;

class OutputLogger extends AbstractLogger
{
    private $output;
    private $verbose;

    public function __construct(OutputInterface $output, $verbose = false)
    {
        $this->output = $output;
        $this->verbose = $verbose;
    }

    public function getOutput()
    {
        return $this->output;
    }

    public function isVerbose()
    {
        return $this->verbose;
    }

    public function log($level, $message, array $context = array())
    {
        if ( ! $this->verbose && $level === 'debug') {
            return;
        }

        $this->output->write($message);
    }
}