<?php

namespace Scrutinizer\Cli;

use Psr\Log\AbstractLogger;
use Symfony\Component\Console\Output\OutputInterface;

class OutputLogger extends AbstractLogger
{
    private $output;

    public function __construct(OutputInterface $output)
    {
        $this->output = $output;
    }

    public function log($level, $message, array $context = array())
    {
        $this->output->writeln($message);
    }
}