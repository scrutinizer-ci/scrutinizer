<?php

namespace Scrutinizer\Cli;

use Scrutinizer\Cli\Command\ConfigReferenceCommand;

use Scrutinizer\Cli\Command\RunCommand;
use Symfony\Component\Console\Application as BaseApplication;

class Application extends BaseApplication
{
    protected function getDefaultCommands()
    {
        $commands = parent::getDefaultCommands();
        $commands[] = new RunCommand();
        $commands[] = new ConfigReferenceCommand();

        return $commands;
    }
}