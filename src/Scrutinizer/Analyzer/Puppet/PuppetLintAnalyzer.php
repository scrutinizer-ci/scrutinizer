<?php

namespace Scrutinizer\Analyzer\Puppet;
use Scrutinizer\Model\Comment;
use PhpOption\Some;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Scrutinizer\Analyzer\AnalyzerInterface;
use Scrutinizer\Util\XmlUtils;
use Scrutinizer\Config\ConfigBuilder;
use Scrutinizer\Model\File;
use Scrutinizer\Model\Project;
use Scrutinizer\Analyzer\AbstractFileAnalyzer;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;


/**
 * Integrates puppet-lint
 *
 * @doc-path tools/puppet/puppet-lint/
 * @display-name puppet-lint
 */
class PuppetLintAnalyzer implements AnalyzerInterface, LoggerAwareInterface
{
    use LoggerAwareTrait;

    /**
     * Builds the configuration structure of this analyzer.
     *
     * This is comparable to Symfony2's default builders except that the
     * ConfigBuilder does add a unified way to enable and disable analyzers,
     * and also provides a unified basic structure for all analyzers.
     *
     * You can read more about how to define your configuration at
     * http://symfony.com/doc/current/components/config/definition.html
     *
     * @param ConfigBuilder $builder
     *
     * @return void
     */
    public function buildConfig(ConfigBuilder $builder)
    {
        $builder
            ->info('Check that your Puppet manifests conform to the PuppetLabs style guide.')
            ->disableDefaultFilter()
            ->globalConfig()
                ->scalarNode('command')
                    ->attribute('show_in_editor', false)
                ->end()
            ->end()
        ;
    }

    /**
     * The name of this analyzer.
     *
     * Should be a lower-case string with "_" as separators.
     *
     * @return string
     */
    public function getName()
    {
        return 'puppet_lint';
    }

    /**
     * Analyzes the given project.
     *
     * @param Project $project
     *
     * @return void
     */
    public function scrutinize(Project $project)
    {
        $command = $project->getGlobalConfig('command', new Some(__DIR__.'/../../../../vendor/bin/puppet-lint'))
                        .' --log-format \'"%{path}","%{linenumber}","%{kind}","%{check}","%{message}"\' '
                        .$project->getDir();

        $this->logger->info('$ '.$command."\n");
        $proc = new Process($command.' '.$project->getDir());
        $proc->setTimeout(600);
        $proc->setIdleTimeout(180);
        $proc->setPty(true);
        $exitcode = $proc->run();
        if ($exitcode > 1) {
            throw new ProcessFailedException($proc);
        }

        $output = $proc->getOutput();
        $violations = explode(PHP_EOL, $output);

        foreach ($violations as $violation) {
            $segments = str_getcsv($violation);
            if (count($segments) !== 5) {
                continue;
            }
            list($path, $linenumber, $kind, $check, $message) = $segments;
            $path = str_replace($project->getDir(), "", $path);
            $project->getFile($path)->map(function(File $file) use ($linenumber, $kind, $check, $message) {
               $file->addComment($linenumber, new Comment(
               $this->getName(),
               $this->getName().'.'.$check, $message, [''])
               );
            });
        }
    }
}
