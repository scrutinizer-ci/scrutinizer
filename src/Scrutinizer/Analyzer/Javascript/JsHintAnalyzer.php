<?php

namespace Scrutinizer\Analyzer\Javascript;

use PhpOption\Some;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Scrutinizer\Cache\CacheAwareInterface;
use Scrutinizer\Cache\CacheAwareTrait;
use Scrutinizer\Util\XmlUtils;
use Monolog\Logger;
use Scrutinizer\Util\NameGenerator;
use Scrutinizer\Analyzer\FileTraversal;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;
use Scrutinizer\Analyzer\AnalyzerInterface;
use Scrutinizer\Config\ConfigBuilder;
use Scrutinizer\Model\Comment;
use Scrutinizer\Model\Project;
use Scrutinizer\Model\File;

/**
 * This provides integration for the node.js CLI version of JSHint.
 *
 * @see https://github.com/jshint/jshint
 *
 * @doc-path tools/javascript/jshint/
 * @display-name JSHint
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class JsHintAnalyzer implements AnalyzerInterface, LoggerAwareInterface, CacheAwareInterface
{
    use LoggerAwareTrait, CacheAwareTrait;

    private $names;

    public function __construct()
    {
        $this->names = new NameGenerator();
    }

    public function scrutinize(Project $project)
    {
        FileTraversal::create($project, $this, 'analyze')
            ->setLogger($this->logger)
            ->setExtensions($project->getGlobalConfig('extensions'))
            ->traverse();
    }

    public function getName()
    {
        return 'js_hint';
    }

    public function buildConfig(ConfigBuilder $builder)
    {
        $builder
            ->info('Runs the JSHint static analysis tool.')
            ->globalConfig()
                ->scalarNode('command')
                    ->attribute('show_in_editor', false)
                ->end()
                ->booleanNode('use_native_config')
                    ->info('Whether to use JSHint\'s native config file, .jshintrc.')
                    ->attribute('label', ' ')
                    ->attribute('help_inline', 'Use JSHint\'s native config file, .jshintrc')
                    ->defaultTrue()
                ->end()
                ->arrayNode('extensions')
                    ->attribute('show_in_editor', false)
                    ->requiresAtLeastOneElement()
                    ->defaultValue(array('js'))
                    ->prototype('scalar')->end()
                ->end()
            ->end()
            ->perFileConfig('variable')
                ->info('All options that are supported by JSHint (see http://jshint.com/docs/); only available when "use_native_config" is set to "false".')
                ->defaultValue(array())
            ->end()
        ;
    }

    private function getJsHintPath()
    {
        $localPath = __DIR__.'/../../../../node_modules/.bin/jshint';
        if (is_file($localPath)) {
            return $localPath;
        }

        return 'jshint';
    }

    public function analyze(Project $project, File $file)
    {
        $config = $this->parseConfig($project, $file);

        $this->cache
            ->withCache(
                $file,
                'result.'.substr(sha1($config), 0, 6),
                function() use ($project, $file, $config) { return $this->runJsHint($project, $file, $config); },
                function($result) use ($file) { $this->parseOutput($file, $result); }
            )
        ;
    }

    private function parseConfig(Project $project, File $file)
    {
        if ($project->getGlobalConfig('use_native_config')) {
            return $this->findNativeConfig($project, $file);
        }

        return json_encode($project->getFileConfig($file), JSON_FORCE_OBJECT);
    }

    private function runJsHint(Project $project, File $file, $config)
    {
        $cfgFile = tempnam(sys_get_temp_dir(), 'jshint');
        file_put_contents($cfgFile, $config);

        $inputFile = tempnam(sys_get_temp_dir(), 'jshint_input');
        rename($inputFile, $inputFile = $inputFile.'.js');
        file_put_contents($inputFile, $file->getContent());

        $proc = new Process($project->getGlobalConfig('command', new Some($this->getJsHintPath()))
                                .' --checkstyle-reporter --config '.escapeshellarg($cfgFile).' '.escapeshellarg($inputFile));
        $proc->run();

        unlink($cfgFile);
        unlink($inputFile);

        if ($proc->getExitCode() > 2 || $proc->getOutput() == '') {
            throw new ProcessFailedException($proc);
        }

        return $proc->getOutput();
    }

    private function parseOutput(File $file, $output)
    {
        $xml = XmlUtils::safeParse($output);

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

            $line = (integer) $attrs->line;
            if ($line === 0) {
                $this->logger->error(sprintf('JSHint config error when analyzing "%s": %s', $file->getPath(), $message));

                continue;
            }

            $file->addComment($line, new Comment($this->getName(), (string) $attrs->source, $message, $params));
        }
    }

    private function findNativeConfig(Project $project, File $file)
    {
        $path = $file->getPath();
        $newPath = null;

        while ($path !== $newPath = dirname($path)) {
            $path = $newPath;

            $configFile = $project->getFile($this->getConfigPath($path));
            if ($configFile->isDefined()) {
                return $configFile->map(function(File $file) { return $file->getContent(); })->get();
            }
        }

        return '{}';
    }

    private function getConfigPath($dir)
    {
        if ($dir === '.') {
            $dir = '';
        } else {
            $dir .= '/';
        }

        return $dir.'.jshintrc';
    }
}
