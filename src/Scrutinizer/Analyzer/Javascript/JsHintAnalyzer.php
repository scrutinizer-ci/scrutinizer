<?php

namespace Scrutinizer\Analyzer\Javascript;

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
class JsHintAnalyzer implements AnalyzerInterface
{
    public function scrutinize(Project $project)
    {
        FileTraversal::create($project, $this, 'analyze')
            ->setExtensions($project->getGlobalConfig('js_hint.extensions'))
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

        $cfgFile = tempnam(sys_get_temp_dir(), 'jshint_cfg');
        file_put_contents($cfgFile, $config);

        $inputFile = tempnam(sys_get_temp_dir(), 'jshint_input');
        file_put_contents($inputFile.'.js', $file->getContent());

        $proc = new Process('jshint --checkstyle-reporter --config '.escapeshellarg($cfgFile).' '.escapeshellarg($inputFile.'.js'));
        $exitCode = $proc->run();

        unlink($cfgFile);
        unlink($inputFile);
        unlink($inputFile.'.js');

        if ($exitCode > 1) {
            throw new ProcessFailedException($proc);
        }

        $previous = libxml_disable_entity_loader(true);
        $xml = simplexml_load_string($proc->getOutput());
        libxml_disable_entity_loader($previous);

        foreach ($xml->xpath('//error') as $error) {
            // <error line="42" column="36" severity="error"
            //        message="[&apos;username&apos;] is better written in dot notation."
            //        source="[&apos;{a}&apos;] is better written in dot notation." />

            $attrs = $error->attributes();
            $message = (string) $attrs->message;

            $file->addComment((integer) $attrs->line, new Comment((string) $attrs->source, $message));
        }
    }

    private function findNativeConfig(Project $project, File $file)
    {
        $path = $file->getPath();
        $newPath = null;

        while ($path !== $newPath = dirname($path)) {
            $path = $newPath;

            if ($project->hasFile($path.'/.jshintrc')) {
                return $project->getFile($path.'/.jshintrc')->getContent();
            }
        }

        return '{}';
    }
}