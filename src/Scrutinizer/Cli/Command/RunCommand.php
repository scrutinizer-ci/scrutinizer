<?php

namespace Scrutinizer\Cli\Command;

use JMS\Serializer\JsonSerializationVisitor;
use JMS\Serializer\Naming\CamelCaseNamingStrategy;
use JMS\Serializer\Naming\SerializedNameAnnotationStrategy;
use JMS\Serializer\SerializerBuilder;
use Scrutinizer\Cli\OutputHandler;
use Monolog\Handler\FingersCrossedHandler;
use Monolog\Logger;
use Scrutinizer\Cli\OutputLogger;
use Scrutinizer\Model\File;
use Scrutinizer\Model\Project;
use Scrutinizer\Scrutinizer;
use Symfony\Component\Console\Input\InputOption;
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
            ->addOption('format', 'f', InputOption::VALUE_REQUIRED, 'The output format (defaults to plain).', 'plain')
            ->addOption('output-file', null, InputOption::VALUE_REQUIRED, 'The file where to write the output (defaults to stdout).')
            ->addOption('path-file', null, InputOption::VALUE_REQUIRED, 'A file with paths that should be analyzed (by default all paths)')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if ( ! is_dir($dir = $input->getArgument('directory'))) {
            $output->writeln(sprintf('<error>The directory "%s" does not exist.</error>', $dir));

            return 1;
        }

        $paths = array();
        if (null !== $pathFile = $input->getOption('path-file')) {
            if ( ! is_file($pathFile)) {
                throw new \InvalidArgumentException(sprintf('The file "%s" does not exist.', $pathFile));
            }

            $paths = explode("\n", file_get_contents($pathFile));
        }

        $project = (new Scrutinizer(new OutputLogger($output, $input->getOption('verbose'))))->scrutinize($dir, $paths);
        $outputFile = $input->getOption('output-file');

        switch ($input->getOption('format')) {
            case 'json':
                $this->outputJson($output, $project, $outputFile);
                break;

            case 'plain':
                $this->outputPlain($output, $project, $outputFile);
                break;

            default:
                throw new \LogicException(sprintf('Unknown output format "%s".', $input->getOption('format')));
        }

        return 0;
    }

    private function outputJson(OutputInterface $output, Project $project, $outputFile)
    {
        $visitor = new JsonSerializationVisitor(new SerializedNameAnnotationStrategy(new CamelCaseNamingStrategy()));
        $visitor->setOptions(JSON_PRETTY_PRINT);

        $serializer = SerializerBuilder::create()
            ->setSerializationVisitor('json', $visitor)
            ->build();

        if ( ! empty($outputFile)) {
            file_put_contents($outputFile, $serializer->serialize($project, 'json'));

            return;
        }

        $output->write($serializer->serialize($project, 'json'));
    }

    private function outputPlain(OutputInterface $output, Project $project, $outputFile)
    {
        $strOutput = '';
        $first = true;
        $nbFiles = $nbComments = 0;
        foreach ($project->getFiles() as $file) {
            assert($file instanceof File);
            $nbFiles += 1;

            if ( ! $file->hasComments()) {
                continue;
            }

            if ( ! $first) {
                $strOutput .= PHP_EOL;
            }
            $first = false;

            $strOutput .= $file->getPath().PHP_EOL;
            $strOutput .= str_repeat('=', strlen($file->getPath())).PHP_EOL;

            $comments = $file->getComments();
            ksort($comments);

            foreach ($comments as $line => $lineComments) {
                foreach ($lineComments as $comment) {
                    $strOutput .= sprintf('Line %d: %s', $line, $comment).PHP_EOL;
                    $nbComments += 1;
                }
            }
        }

        if ($nbComments > 0) {
            $strOutput .= PHP_EOL;
        }

        $strOutput .= sprintf("Scanned Files: %s, Comments: %s", $nbFiles, $nbComments).PHP_EOL;

        if ( ! empty($outputFile)) {
            file_put_contents($outputFile, $strOutput);

            return;
        }

        $output->write($strOutput);
    }
}
