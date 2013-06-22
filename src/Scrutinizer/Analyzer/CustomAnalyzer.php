<?php

namespace Scrutinizer\Analyzer;

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use Scrutinizer\Analyzer\AnalyzerInterface;
use Scrutinizer\Analyzer\Custom\FileBasedRunner;
use Scrutinizer\Analyzer\Custom\ProjectBasedRunner;
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
    private $fileBasedRunner;
    private $projectBasedRunner;

    public function __construct()
    {
        $this->fileBasedRunner = new FileBasedRunner();
        $this->projectBasedRunner = new ProjectBasedRunner();
    }

    public function setLogger(LoggerInterface $logger)
    {
        $this->fileBasedRunner->setLogger($logger);
        $this->projectBasedRunner->setLogger($logger);
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
            switch ($commandData['scope']) {
                case 'file':
                    $this->fileBasedRunner->run($project, $commandData);
                    break;

                case 'project':
                    $this->projectBasedRunner->run($project, $commandData);
                    break;

                default:
                    throw new \RuntimeException(sprintf('Unknown custom analyzer scope "%s".', $commandData['scope']));
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
                ->validate()
                    ->always(function($v) {
                        switch ($v['scope']) {
                            case 'file':
                                if (false === strpos($v['command'], '%pathname%') && false === strpos($v['command'], '%fixed_pathname%')) {
                                    throw new \Exception('The command must contain the "%pathname%", or "%fixed_pathname%" placeholder.');
                                }
                                break;

                            case 'project':
                                break;

                            default:
                                throw new \LogicException('Previous cases were exhaustive.');
                        }

                        return $v;
                    })
                ->end()

                ->children()
                    ->scalarNode('command')->isRequired()->cannotBeEmpty()->end()
                    ->scalarNode('output_file')->end()
                    ->enumNode('scope')
                        ->info('The scope that this command analyzes. Either the entire project, or the project\'s files separately.')
                        ->values(array('project', 'file'))
                        ->defaultValue('file')
                    ->end()
                    ->scalarNode('iterations')
                        ->info('If the command computes statistical metrics, you can increase the number of iterations to achieve a more reliable estimate.')
                        ->validate()->always(function($v) {
                            if ( ! is_int($v)) {
                                throw new \Exception(sprintf('"iterations" must be an integer, but got "%s".', gettype($v)));
                            }

                            return $v;
                        })->end()
                        ->defaultValue(1)
                    ->end()
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