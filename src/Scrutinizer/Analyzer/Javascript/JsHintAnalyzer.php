<?php

namespace Scrutinizer\Analyzer\Javascript;

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
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
class JsHintAnalyzer implements AnalyzerInterface, LoggerAwareInterface
{
    use LoggerAwareTrait;

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

    public function analyze(Project $project, File $file)
    {
        if ($project->getGlobalConfig('use_native_config')) {
            $config = $this->findNativeConfig($project, $file);
        } else {
            $config = json_encode($project->getFileConfig($file), JSON_FORCE_OBJECT);
        }

        $cfgFile = tempnam(sys_get_temp_dir(), 'jshint');
        file_put_contents($cfgFile, $config);

        $inputFile = tempnam(sys_get_temp_dir(), 'jshint_input');
        rename($inputFile, $inputFile = $inputFile.'.js');
        file_put_contents($inputFile, $file->getContent());

        $proc = new Process('jshint --checkstyle-reporter --config '.escapeshellarg($cfgFile).' '.escapeshellarg($inputFile));
        $proc->run();

        unlink($cfgFile);
        unlink($inputFile);

        if ($proc->getExitCode() > 2 || $proc->getOutput() === '') {
            throw new ProcessFailedException($proc);
        }

        $xml = XmlUtils::safeParse($proc->getOutput());

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

            $file->addComment((integer) $attrs->line, new Comment($this->getName(), (string) $attrs->source, $message, $params));
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
                return $configFile->get()->getContent();
            }
        }

        return '{}';
    }
}