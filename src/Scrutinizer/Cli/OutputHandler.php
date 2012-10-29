<?php

namespace Scrutinizer\Cli;

use Monolog\Formatter\FormatterInterface;

use Symfony\Component\Console\Output\OutputInterface;
use Monolog\Handler\HandlerInterface;

class OutputHandler implements HandlerInterface
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
        $output->writeln($record['message']);

        return true;
    }

    /**
     * Handles a set of records at once.
     *
     * @param array $records The records to handle (an array of record arrays)
    */
    public function handleBatch(array $records)
    {
        foreach ($records as $record) {
            $this->handle($record);
        }
    }

    /**
     * Adds a processor in the stack.
     *
     * @param callable $callback
    */
    public function pushProcessor($callback)
    {
    }

    /**
     * Removes the processor on top of the stack and returns it.
     *
     * @return callable
    */
    public function popProcessor()
    {
    }

    /**
     * Sets the formatter.
     *
     * @param FormatterInterface $formatter
    */
    public function setFormatter(FormatterInterface $formatter)
    {
    }

    /**
     * Gets the formatter.
     *
     * @return FormatterInterface
    */
    public function getFormatter()
    {
    }
}