<?php

namespace Scrutinizer\Analyzer\Custom\Parser;

use Scrutinizer\Analyzer\Custom\ParserInterface;
use Scrutinizer\Model\Project;
use Symfony\Component\Config\Definition\ArrayNode;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\Processor;

class ScrutinizerJsonParser implements ParserInterface
{
    private $node;
    private $processor;

    public function __construct()
    {
        $this->node = $this->getFileSchemaNode();
        $this->processor = new Processor();
    }

    public function getFormat()
    {
        return 'scrutinizer_json';
    }

    public function parse(Project $project, $content)
    {
        $rawResult = json_decode($content, true);
        if (false === $rawResult) {
            throw new \RuntimeException('The JSON content could not be parsed: '.json_last_error());
        }

        return $this->processor->process($this->node, array($rawResult));
    }

    private function getFileSchemaNode()
    {
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
            ->end()
        ;

        return $tb->buildTree();
    }
}