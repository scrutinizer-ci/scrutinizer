<?php

namespace Scrutinizer\Analyzer\Javascript;

use Scrutinizer\Config\ConfigBuilder;
use Scrutinizer\Model\FileIterator;
use Scrutinizer\Model\Project;
use Scrutinizer\Model\File;

class JsLintAnalyzer
{
    public function scrutinize(Project $project)
    {
        FileTraversal::create($project, array($this, 'analyze'))
            ->setExtensions(array('js'))
            ->traverse();
    }

    public function getConfig()
    {
        return ConfigBuilder::create('js_lint')
            ->info('Runs Douglas Crockford\'s JSLint code quality tool.')
            ->children()
                ->enumNode('level')
                    ->values(array('foo', 'bar'))
                    ->defaultValue('foo')
                ->end()
            ->end()
            ->build();
    }

    public function analyze(File $file, array $config)
    {
    }
}