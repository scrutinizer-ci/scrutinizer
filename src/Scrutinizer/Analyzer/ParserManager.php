<?php

namespace Scrutinizer\Analyzer;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

class ParserManager
{
    private $node;
    private $processor;
    private $parsers = array();

    public function __construct()
    {
        foreach (Finder::create()->files()->in(__DIR__.'/Parser')->name('*Parser.php') as $file) {
            /** @var $file SplFileInfo */

            $className = 'Scrutinizer\Analyzer\Parser\\'.$file->getBasename('.php');
            $this->add(new $className);
        }

        $tb = new TreeBuilder();
        $tb->root('{root}', 'array')
            ->children()
                ->arrayNode('comments')
                    ->prototype('array')
                        ->scalarNode('line')->isRequired()->cannotBeEmpty()->end()
                        ->scalarNode('id')->isRequired()->cannotBeEmpty()->end()
                        ->scalarNode('message')->isRequired()->cannotBeEmpty()->end()
                        ->arrayNode('params')
                            ->useAttributeAsKey('name')
                            ->prototype('scalar')->end()
                        ->end()
                    ->end()
                ->end()
                ->scalarNode('fixed_content')->defaultNull()->end()
        ;
        $this->node = $tb->buildTree();

        $this->processor = new Processor();
    }

    public function add(ParserInterface $parser)
    {
        $this->parsers[$parser->getFormat()] = $parser;
    }

    public function parse($format, $output)
    {
        if ( ! isset($this->parsers[$format])) {
            throw new \InvalidArgumentException(sprintf('The format "%s" is not supported.', $format));
        }

        return $this->processor->process($this->node, $this->parsers[$format]->parse($output));
    }

    public function getSupportedFormats()
    {
        return array_keys($this->parsers);
    }
}