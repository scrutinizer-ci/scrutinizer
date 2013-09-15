<?php

namespace Scrutinizer\Analyzer\Php;

use Scrutinizer\Analyzer\AbstractFileAnalyzer;
use Scrutinizer\Config\ConfigBuilder;
use Scrutinizer\Model\File;
use Scrutinizer\Model\Project;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

/**
 * Runs PHP CS Fixer.
 *
 * @doc-path tools/php/cs-fixer/
 * @display-name PHP CS Fixer
 */
class CsFixerAnalyzer extends AbstractFileAnalyzer
{
    private $tmpDir;
    private $fs;

    public function __construct()
    {
        $this->fs = new Filesystem();
        $this->tmpDir = tempnam(sys_get_temp_dir(), 'php-cs-fixer');
        $this->fs->remove($this->tmpDir);
        $this->fs->mkdir($this->tmpDir);
    }

    public function getName()
    {
        return 'php_cs_fixer';
    }

    protected function getInfo()
    {
        return 'Runs the PHP CS Fixer (http://http://cs.sensiolabs.org/).';
    }

    protected function getDefaultExtensions()
    {
        return array('php');
    }

    protected function buildConfigInternal(ConfigBuilder $builder)
    {
        $builder
            ->globalConfig()
                ->scalarNode('command')
                    ->defaultValue('php-cs-fixer')
                ->end()
            ->end()
            ->perFileConfig('array')
                ->addDefaultsIfNotSet()
                ->fixXmlConfig('fixer')
                ->children()
                    ->enumNode('level')
                        ->attribute('label', 'Fixing Level')
                        ->attribute('choices', array(
                            'psr0' => 'PSR 0',
                            'psr1' => 'PSR 1',
                            'psr2' => 'PSR 2',
                            'all'  => 'All Fixers',
                            'custom' => 'Custom Fixers (see below)',
                        ))
                        ->values(array('psr0', 'psr1', 'psr2', 'all', 'custom'))
                        ->defaultValue('psr1')
                    ->end()
                    ->arrayNode('fixers')
                        ->addDefaultsIfNotSet()
                        ->attribute('type', 'choice')
                        ->attribute('depends_on', array('level' => 'custom'))
                        ->beforeNormalization()->always(function($v) {
                            if (is_array($v) && ! empty($v) && is_string(reset($v))) {
                                $values = array_combine($v, array_fill(0, count($v), true));

                                return $values;
                            }

                            return $v;
                        })->end()
                        ->children()
                            ->booleanNode('indentation')
                                ->attribute('label', 'Code must use 4 spaces for indenting, not tabs.')
                                ->defaultFalse()
                            ->end()
                            ->booleanNode('linefeed')
                                ->attribute('label', 'All PHP files must use the Unix LF (linefeed) line ending.')
                                ->defaultFalse()
                            ->end()
                            ->booleanNode('trailing_spaces')
                                ->attribute('label', 'Remove trailing whitespace at the end of lines.')
                                ->defaultFalse()
                            ->end()
                            ->booleanNode('unused_use')
                                ->attribute('label', 'Unused use statements must be removed.')
                                ->defaultFalse()
                            ->end()
                            ->booleanNode('phpdoc_params')
                                ->attribute('label', 'All items of the @param phpdoc tags must be aligned vertically.')
                                ->defaultFalse()
                            ->end()
                            ->booleanNode('visibility')
                                ->attribute('label', 'Visibility must be declared on all properties and methods; abstract and final must be declared before the visibility; static must be declared after the visibility.')
                                ->defaultFalse()
                            ->end()
                            ->booleanNode('return')
                                ->attribute('label', 'An empty line feed should precede a return statement.')
                                ->defaultFalse()
                            ->end()
                            ->booleanNode('short_tag')
                                ->attribute('label', 'PHP code must use the long <?php ?> tags or the short-echo <?= ?> tags; it must not use the other tag variations.')
                                ->defaultFalse()
                            ->end()
                            ->booleanNode('braces')
                                ->attribute('label', 'Opening braces for classes, interfaces, traits and methods must go on the next line, and closing braces must go on the next line after the body. Opening braces for control structures must go on the same line, and closing braces must go on the next line after the body.')
                                ->defaultFalse()
                            ->end()
                            ->booleanNode('include')
                                ->attribute('label', 'Include and file path should be divided with a single space. File path should not be placed under brackets.')
                                ->defaultFalse()
                            ->end()
                            ->booleanNode('php_closing_tag')
                                ->attribute('label', 'The closing ?> tag MUST be omitted from files containing only PHP.')
                                ->defaultFalse()
                            ->end()
                            ->booleanNode('extra_empty_lines')
                                ->attribute('label', 'Removes extra empty lines.')
                                ->defaultFalse()
                            ->end()
                            ->booleanNode('controls_spaces')
                                ->attribute('label', 'A single space should be between: the closing brace and the control, the control and the opening parenthese, the closing parenthese and the opening brace.')
                                ->defaultFalse()
                            ->end()
                            ->booleanNode('elseif')
                                ->attribute('label', 'The keyword elseif should be used instead of else if so that all control keywords looks like single words.')
                                ->defaultFalse()
                            ->end()
                            ->booleanNode('eof_ending')
                                ->attribute('label', 'A file must always end with an empty line feed.')
                                ->defaultFalse()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;
    }

    public function analyze(Project $project, File $file)
    {
        $command = $project->getGlobalConfig('command');
        $options = $this->getCommandOptions($project, $file);

        $fixedFile = $file->getOrCreateFixedFile();
        $tmpPath = $this->tmpDir.'/'.$file->getPath();

        if ( ! is_dir(dirname($tmpPath)) && false === @mkdir(dirname($tmpPath), 0777, true)) {
            throw new \RuntimeException(sprintf('The temporary directory "%s" could not be created.', dirname($tmpPath)));
        }

        file_put_contents($tmpPath, $fixedFile->getContent());

        // For some reason, this command sometimes fails on the first try. So until we find the real cause for this, as
        // a workaround we will simply try it again.
        $i = 0;
        $failedProc = null;
        do {
            $proc = new Process($command.' fix '.escapeshellarg($tmpPath).' '.$options);
            $proc->setTimeout(300);
            if (0 === $proc->run()) {
                $failedProc = null;
                break;
            }

            if (null === $failedProc) {
                $failedProc = $proc;
            }

            $i += 1;
        } while ($i < 3);

        if (null !== $failedProc) {
            throw new ProcessFailedException($failedProc);
        }

        $fixedFile->setContent(file_get_contents($tmpPath));
        unlink($tmpPath);
    }

    private function getCommandOptions(Project $project, File $file)
    {
        $level = $project->getFileConfig($file, 'level');
        if ($level === 'custom') {
            $filters = array_filter($project->getFileConfig($file, 'fixers'));
            if (empty($filters)) {
                throw new \RuntimeException(sprintf('The fixing level was set to "custom", but not fixers were selected.'));
            }

            return '--fixers='.implode(',', array_keys($filters));
        }

        return '--level='.$level;
    }
}
