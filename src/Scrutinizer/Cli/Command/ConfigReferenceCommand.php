<?php

namespace Scrutinizer\Cli\Command;

use Scrutinizer\Config\ConfigReferenceDumper;
use Scrutinizer\Scrutinizer;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Command\Command;

class ConfigReferenceCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('config-reference')
            ->setDescription('Prints out a configuration reference.')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $scrutinizer = new Scrutinizer();
        $configTree = $scrutinizer->getConfiguration()->getTree();

        $dumper = new ConfigReferenceDumper();
        $output->write($dumper->dumpNode($configTree));
    }
}