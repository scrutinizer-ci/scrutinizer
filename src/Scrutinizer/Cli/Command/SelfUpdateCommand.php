<?php

namespace Scrutinizer\Cli\Command;

use Scrutinizer\Scrutinizer;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class SelfUpdateCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('self-update')
            ->setDescription('Updates scrutinizer to the latest available version.')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if (Scrutinizer::REVISION === implode('', array('@', 'revision', '@'))) {
            throw new \RuntimeException('The "self-update" command is only available for compiled phar files which you can obtain at "https://scrutinizer-ci.com/scrutinizer.phar".');
        }

        $latest = @file_get_contents('https://scrutinizer-ci.com/scrutinizer.phar.sha1');
        if (false === $latest) {
            throw new \RuntimeException(sprintf('Could not fetch latest version. Please try again later.'));
        }

        if (Scrutinizer::REVISION !== $latest) {
            $output->writeln(sprintf('Updating from <info>%s</info> to <info>%s</info>.', substr(Scrutinizer::REVISION, 0, 6), substr($latest, 0, 6)));


            $tmpFile = tempnam(sys_get_temp_dir(), 'scrutinizer').'.phar';
            if (false === @copy('https://scrutinizer-ci.com/scrutinizer.phar', $tmpFile)) {
                throw new \RuntimeException(sprintf('Could not download new version'));
            }

            // Check download is valid.
            $phar = new \Phar($tmpFile);
            unset($phar);

            if (false === @rename($tmpFile, $_SERVER['argv'][0])) {
                throw new \RuntimeException(sprintf('Could not deploy new file to "%s".', $_SERVER['argv'][0]));
            }
        } else {
            $output->writeln('You are already using the latest version.');
        }
    }
}