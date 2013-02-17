<?php

namespace Scrutinizer\Cli;

use Scrutinizer\Cli\Command\ConfigReferenceCommand;

use Scrutinizer\Cli\Command\RunCommand;
use Symfony\Component\Console\Application as BaseApplication;

class Application extends BaseApplication
{
    public function __construct()
    {
        parent::__construct('scrutinizer', '0.1');
    }

    protected function getDefaultCommands()
    {
        $commands = parent::getDefaultCommands();
        $commands[] = new RunCommand();
        $commands[] = new ConfigReferenceCommand();

        return $commands;
    }
}