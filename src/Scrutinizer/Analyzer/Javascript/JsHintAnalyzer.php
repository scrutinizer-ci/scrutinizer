<?php

namespace Scrutinizer\Analyzer\Javascript;

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
class JsLintAnalyzer implements AnalyzerInterface
{
    public function scrutinize(Project $project)
    {
        FileTraversal::create($project, $this, 'analyze')
            ->setExtensions($project->getGlobalConfig('js_hint.extensions'))
            ->traverse();
    }

    public function buildConfig(ConfigBuilder $builder)
    {
        $builder
            ->analyzer('js_hint', 'Runs the JSHint static analysis tool.')
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
            $config = json_encode($project->getFileConfig($file, 'js_hint'));
        }

        $cfgFile = tempnam(sys_get_temp_dir(), 'jshint_cfg');
        file_put_contents($nativeConfig);

        $inputFile = tempnam(sys_get_temp_dir(), 'jshint_input');
        file_put_contents($file->getContent());

        $proc = new Process('jshint --checkstyle-reporter --config '.escapeshellarg($cfgFile).' '.escapeshellarg($inputFile));
        if (0 !== $proc->run()) {
            throw new ProcessFailedException($proc);
        }

        $previous = libxml_disable_entity_loader(true);
        $xml = simplexml_load_string($proc->getOutput());
        libxml_disable_entity_loader($previous);

        foreach ($xml->xpath('//error') as $error) {
            // <error line="42" column="36" severity="error" message="[&apos;username&apos;] is better written in dot notation." source="[&apos;{a}&apos;] is better written in dot notation." />

            $attrs = $error->attributes();
            $message = (string) $attrs->message;

            $file->addComment((integer) $attrs->line, new Comment((string) $attrs->source, $message));
        }
    }

    private function findNativeConfig(Project $project, File $file)
    {
        $path = $file->getPath();

        while ('' !== $path = dirname($path)) {
            if ($project->hasFile($path.'/.jshintrc')) {
                return $project->getFile($path.'/.jshintrc')->getContent();
            }
        }

        return '{}';
    }
}