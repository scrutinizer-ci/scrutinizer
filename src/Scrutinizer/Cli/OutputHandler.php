<?php

namespace Scrutinizer\Cli;

use Monolog\Handler\AbstractHandler;
use Monolog\Formatter\FormatterInterface;
use Symfony\Component\Console\Output\OutputInterface;

class OutputHandler extends AbstractHandler
{
    private $output;

    public function __construct(OutputInterface $output)
    {
        $this->output = $output;
    }

    /**
     * Checks whether the given record will be handled by this handler.
     *
     * This is mostly done for performance reasons, to avoid calling processors for nothing.
     *
     * @param array $record
     *
     * @return Boolean
     */
    public function isHandling(array $record)
    {
        return true;
    }

    /**
     * Handles a record.
     *
     * The return value of this function controls the bubbling process of the handler stack.
     *
     * @param  array   $record The record to handle
     * @return Boolean True means that this handler handled the record, and that bubbling is not permitted.
     *                 False means the record was either not processed or that this handler allows bubbling.
    */
    public function handle(array $record)
    {
        $this->output->writeln($record['message']);

        return false;
    }
}