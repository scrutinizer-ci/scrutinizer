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
                ->scalarNode('flags')
                    ->info('Define checks which you would like to skip separated by spaces, e.g. "--no-case_without_default-check --no-other-check".')
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
        $analysisDir = $this->prepareProjectForAnalysis($project);

        $command = $project->getGlobalConfig('command', new Some(__DIR__.'/../../../../vendor/bin/puppet-lint'));
        $command .= ' -f --log-format \'%{path},%{linenumber},%{kind},%{check},%{message}\' ';
        $command .= $project->getGlobalConfig('flags', new Some('')).' ';
        $command .= $analysisDir;

        $this->logger->info('$ '.$command."\n");
        $proc = new Process($command.' '.$analysisDir);
        $proc->setTimeout(600);
        $proc->setIdleTimeout(180);
        $proc->setPty(true);

        if ($proc->run() > 1) {
            throw new ProcessFailedException($proc);
        }

        $output = $proc->getOutput();

        $violations = explode(PHP_EOL, $output);
        foreach ($violations as $violation) {
            $segments = explode(",", $violation, 5);
            if (count($segments) !== 5) {
                continue;
            }

            list($path, $line, $kind, $check, $message) = $segments;
            $relativePath = substr($path, strlen($analysisDir) + 1);

            $project->getFile($relativePath)->map(function(File $file) use ($analysisDir, $line, $kind, $check, $message) {
                $file->addComment($line, new Comment(
                    $this->getName(),
                    $this->getName().'.'.$check, $message)
                );

                $file->getOrCreateFixedFile()->setContent(file_get_contents($analysisDir.'/'.$file->getPath()));
            });

        }

        $this->cleanUpAfterAnalysis($analysisDir);

    }

    private function cleanUpAfterAnalysis($analysisDir)
    {
        $proc = new Process('rm -rf '.$analysisDir);
        $proc->run();
    }

    private function prepareProjectForAnalysis(Project $project)
    {
        $analysisDir = tempnam(sys_get_temp_dir(), 'puppet-lint-project');
        unlink($analysisDir);

        $proc = new Process('cp -R '.$project->getDir().' '.$analysisDir);
        $proc->mustRun();

        return $analysisDir;
    }
}
