<?php

namespace Scrutinizer\Analyzer\Javascript;

use Scrutinizer\Util\XmlUtils;

use Monolog\Logger;
use Scrutinizer\Analyzer\LoggerAwareInterface;
use Scrutinizer\Util\NameGenerator;
use Scrutinizer\Analyzer\FileTraversal;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;
use Scrutinizer\Analyzer\AnalyzerInterface;
use Scrutinizer\Config\ConfigBuilder;
use Scrutinizer\Model\Comment;
use Scrutinizer\Model\FileIterator;
use Scrutinizer\Model\Project;
use Scrutinizer\Model\File;

/**
 * This provides integration for the node.js CLI version of JSHint.
 *
 * @see https://github.com/jshint/jshint
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class JsHintAnalyzer implements AnalyzerInterface, LoggerAwareInterface, \Scrutinizer\Analyzer\FilesystemAwareInterface, \Scrutinizer\Analyzer\ProcessExecutorAwareInterface
{
    private $names;
    private $logger;
    private $executor;
    private $fs;

    public function __construct()
    {
        $this->names = new NameGenerator();
    }

    public function setFilesystem(\Scrutinizer\Util\FilesystemInterface $fs)
    {
        $this->fs = $fs;
    }

    public function setProcessExecutor(\Scrutinizer\Util\ProcessExecutorInterface $executor)
    {
        $this->executor = $executor;
    }

    public function setLogger(Logger $logger)
    {
        $this->logger = $logger;
    }

    public function scrutinize(Project $project)
    {
        FileTraversal::create($project, $this, 'analyze')
            ->setLogger($this->logger)
            ->setExtensions($project->getGlobalConfig('js_hint.extensions'))
            ->traverse();
    }

    public function getName()
    {
        return 'js_hint';
    }

    public function getMetrics()
    {
        return array();
    }

    public function buildConfig(ConfigBuilder $builder)
    {
        $builder
            ->info('Runs the JSHint static analysis tool.')
            ->globalConfig()
                ->booleanNode('use_native_config')
                    ->info('Whether to use JSHint\'s native config file, .jshintrc.')
                    ->defaultTrue()
                ->end()
                ->arrayNode('extensions')
                    ->requiresAtLeastOneElement()
                    ->defaultValue(array('js'))
                    ->prototype('scalar')->end()
                ->end()
            ->end()
            ->perFileConfig('variable')
                ->info('All options that are supported by JSHint (see http://jshint.com/docs/); only availabe when "use_native_config" is disabled.')
                ->defaultValue(array())
            ->end()
        ;
    }

    public function analyze(Project $project, File $file)
    {
        if ($project->getGlobalConfig('js_hint.use_native_config')) {
            $config = $this->findNativeConfig($project, $file);
        } else {
            $config = json_encode($project->getFileConfig($file, 'js_hint'), JSON_FORCE_OBJECT);
        }

        $cfgFile = $this->fs->createTempFile($config);

        $inputFile = $this->fs->createTempFile($file->getContent());
        $inputFile->rename($inputFile->getName().'.js');

        $proc = new Process('jshint --checkstyle-reporter --config '.escapeshellarg($cfgFile->getName()).' '.escapeshellarg($inputFile->getName()));
        $executedProc = $this->executor->execute($proc);

        $cfgFile->delete();
        $inputFile->delete();

        if ($executedProc->getExitCode() > 1
                || ($executedProc->getExitCode() === 1 && $executedProc->getOutput() === '')) {
            throw new ProcessFailedException($executedProc);
        }

        $xml = XmlUtils::safeParse($executedProc->getOutput());

        foreach ($xml->xpath('//error') as $error) {
            // <error line="42" column="36" severity="error"
            //        message="[&apos;username&apos;] is better written in dot notation."
            //        source="[&apos;{a}&apos;] is better written in dot notation." />

            $attrs = $error->attributes();
            $message = (string) $attrs->message;
            $params = array();

            // We are trying to extract variable parts from the message. Currently,
            // this algorithm is simply extracting everything wrapped in quotes.
            if (preg_match_all('#("[^"]+"|\'[^\']+\')#', $message, $matches)) {
                $this->names->reset();

                foreach ($matches[1] as $value) {
                    $params[$this->names->next()] = substr($value, 1, -1);
                }
            }

            $file->addComment((integer) $attrs->line, new Comment((string) $attrs->source, $message, $params));
        }
    }

    private function findNativeConfig(Project $project, File $file)
    {
        $path = $file->getPath();
        $newPath = null;

        while ($path !== $newPath = dirname($path)) {
            $path = $newPath;

            $configFile = $project->getFile($path.'/.jshintrc');
            if ($configFile->isDefined()) {
                return $configFile->get();
            }
        }

        return '{}';
    }
}