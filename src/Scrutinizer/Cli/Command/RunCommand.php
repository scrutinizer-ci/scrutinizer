<?php

namespace Scrutinizer\Cli\Command;

use Scrutinizer\Model\File;
use Scrutinizer\Scrutinizer;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Command\Command;

class RunCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('run')
            ->setDescription('Runs the scrutinizer.')
            ->addArgument('directory', InputArgument::REQUIRED, 'The directory that should be scrutinized.')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if ( ! is_dir($dir = $input->getArgument('directory'))) {
            $output->writeln(sprintf('<error>The directory "%s" does not exist.</error>', $dir));

            return 1;
        }

        $scrutinizer = new Scrutinizer();
        $project = $scrutinizer->scrutinize($dir);

        // TODO: Add other formatters.
        $first = true;
        foreach ($project->getFiles() as $file) {
            assert($file instanceof File);

            if ( ! $file->hasComments()) {
                continue;
            }

            if ( ! $first) {
                $output->writeln('');
            }
            $first = false;

            $output->writeln($file->getPath());
            $output->writeln(str_repeat('=', strlen($file->getPath())));

            $comments = $file->getComments();
            ksort($comments);

            foreach ($comments as $line => $lineComments) {
                foreach ($lineComments as $comment) {
                    $output->writeln(sprintf('Line %d: %s', $line, $comment));
                }
            }
        }

        return 0;
    }
}