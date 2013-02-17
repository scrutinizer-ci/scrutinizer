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
            ->perFileConfig('array')
                ->addDefaultsIfNotSet()
                ->fixXmlConfig('fixer')
                ->children()
                    ->enumNode('level')
                        ->values(array('psr0', 'psr1', 'psr2', 'all'))
                        ->defaultValue('psr1')
                    ->end()
                    ->arrayNode('fixers')->prototype('scalar')->end()->end()
                ->end()
            ->end()
        ;
    }

    public function analyze(Project $project, File $file)
    {
        $fixers = $project->getFileConfig($file, 'fixers');

        $options = '';
        if ( ! empty($fixers)) {
            $options .= '--fixers='.implode(',', $fixers);
        } else {
            $options .= '--level='.$project->getFileConfig($file, 'level');
        }

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
            $proc = new Process('php-cs-fixer fix '.escapeshellarg($tmpPath).' '.$options);
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
}