<?php

namespace Scrutinizer\Analyzer\Custom;

use Scrutinizer\Logger\LoggableProcess;
use Scrutinizer\Model\Comment;
use Scrutinizer\Model\File;
use Scrutinizer\Model\Project;
use Scrutinizer\Util\PathUtils;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

class FileBasedRunner extends AbstractRunner
{
    private $outputConfigNode;
    private $configProcessor;

    public function __construct()
    {
        $tb = new TreeBuilder();

        $paramsNode = $tb->root('{root}', 'array')
            ->children()
                ->scalarNode('fixed_content')->defaultNull()->end()
                ->arrayNode('comments')
                    ->prototype('array')
                        ->children()
                            ->scalarNode('line')->isRequired()->cannotBeEmpty()->end()
                            ->scalarNode('id')->isRequired()->cannotBeEmpty()->end()
                            ->scalarNode('message')->isRequired()->cannotBeEmpty()->end()
                            ->arrayNode('params')
                                ->useAttributeAsKey('name')
                                ->prototype('scalar')->end()
        ;

        if (method_exists($paramsNode, 'normalizeKeys')) {
            $paramsNode->normalizeKeys(false);
        }
        $this->outputConfigNode = $tb->buildTree();

        $this->configProcessor = new Processor();
    }

    public function run(Project $project, array $commandData)
    {
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

                for ($i=0; $i<$commandData['iterations']; $i++) {
                    $proc = new LoggableProcess(strtr($commandData['command'], $placeholders));
                    $proc->setTimeout(300);
                    $proc->setLogger($this->logger);
                    $exitCode = $proc->run();

                    if (0 === $exitCode) {
                        $output = isset($commandData['output_file']) ? file_get_contents($commandData['output_file'])
                            : $proc->getOutput();

                        $parsedOutput = $this->configProcessor->process($this->outputConfigNode, array(json_decode($output, true)));

                        // Safe-guard to avoid the same comments being added multiple times.
                        if ($commandData['iterations'] > 1 && ( ! empty($parsedOutput['comments']) || ! empty($parsedOutput['fixed_content']))) {
                            throw new \LogicException(sprintf('Commands which add comments, or patches cannot have more than one iteration. Offending command: %s', $commandData['command']));
                        }

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
                }

                unlink($fixedContentFile);
            });
        }
    }

    public function getOutputConfigNode()
    {
        return $this->outputConfigNode;
    }
}