<?php

namespace Scrutinizer\Analyzer;

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Scrutinizer\Analyzer\AnalyzerInterface;
use Scrutinizer\Analyzer\FileTraversal;
use Scrutinizer\Config\ConfigBuilder;
use Scrutinizer\Logger\LoggableProcess;
use Scrutinizer\Model\Comment;
use Scrutinizer\Model\File;
use Scrutinizer\Model\FixedFile;
use Scrutinizer\Model\Project;
use Scrutinizer\Util\DiffUtils;
use Scrutinizer\Util\PathUtils;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

/**
 * Runs custom tool commands and analyzes the results.
 *
 * We support different output formats; all of which you can find in the Parser/ sub-namespace.
 *
 * @doc-path tools/custom-commands/
 * @display-name Custom Command
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class CustomAnalyzer implements AnalyzerInterface, LoggerAwareInterface
{
    use LoggerAwareTrait;

    private $parser;

    public function __construct()
    {
        $this->parser = new ParserManager();
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
        foreach ($project->getAnalyzerConfig() as $commandData) {
            foreach (Finder::create()->files()->in($project->getDir()) as $file) {
                /** @var $file SplFileInfo */

                if (PathUtils::isFiltered($file->getRelativePathname(), $commandData['filter'])) {
                    continue;
                }

                if ( ! $project->isAnalyzed($file->getRelativePathname())) {
                    continue;
                }

                $project->getFile($file->getRelativePathname())->map(function(File $projectFile) use ($commandData, $file) {
                    $fixedContentFile = tempnam(sys_get_temp_dir(), 'fixed');
                    file_put_contents($fixedContentFile, $projectFile->getOrCreateFixedFile()->getContent());

                    $placeholders = array(
                        '%pathname%' => escapeshellarg($file->getRealPath()),
                        '%fixed_pathname%' => escapeshellarg($fixedContentFile),
                    );

                    $proc = new LoggableProcess(strtr($commandData['command'], $placeholders));
                    $proc->setLogger($this->logger);
                    $exitCode = $proc->run();

                    unlink($fixedContentFile);

                    if (0 === $exitCode) {
                        $output = isset($customAnalyzer['output_file']) ? file_get_contents($customAnalyzer['output_file'])
                            : $proc->getOutput();

                        $parsedOutput = $this->parser->parse($commandData['output_format'], $output);

                        foreach ($parsedOutput['comments'] as $comment) {
                            $projectFile->addComment($comment['line'], new Comment(
                                'custom_commands',
                                $comment['id'],
                                $comment['message'],
                                $comment['params']
                            ));
                        }

                        if (null !== $parsedOutput['fixed_content']) {
                            $projectFile->getFixedFile()->get()->setContent($parsedOutput['fixed_content']);
                        }
                    } else {
                        $this->logger->error('An error occurred while executing "'.$proc->getCommandLine().'"; ignoring result.');
                    }
                });
            }
        }
    }

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
            ->info('Runs Custom Commands')
            ->disableDefaultSettings()
            ->prototype('array')
                ->children()
                    ->scalarNode('command')
                        ->isRequired()->cannotBeEmpty()
                        ->validate()
                            ->always(function($v) {
                                if (false === strpos($v, '%pathname%') && false === strpos($v, '%fixed_pathname%')) {
                                    throw new \Exception('Command must contain the "%pathname%", or "%fixed_pathname%" placeholder.');
                                }

                                return $v;
                            })
                        ->end()
                    ->end()
                    ->scalarNode('output_file')->end()
                    ->enumNode('output_format')->isRequired()->values($this->parser->getSupportedFormats())->end()
                    ->arrayNode('filter')
                        ->addDefaultsIfNotSet()
                        ->fixXmlConfig('path')
                        ->fixXmlConfig('excluded_path')
                        ->children()
                            ->arrayNode('paths')->prototype('scalar')->end()->end()
                            ->arrayNode('excluded_paths')->prototype('scalar')->end()->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;
    }

    /**
     * Returns metadata for metrics that are measured by this analyzer.
     *
     * @return array<Metric>
     */
    public function getMetrics()
    {
        return array();
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
        return 'custom_commands';
    }
}