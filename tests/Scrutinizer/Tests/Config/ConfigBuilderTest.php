<?php

namespace Scrutinizer\Tests\Config;

use Symfony\Component\Config\Definition\Processor;
use Scrutinizer\Config\ConfigBuilder;

class ConfigBuilderTest extends \PHPUnit_Framework_TestCase
{
    private $builder;

    public function testCreateAnalzerConfig()
    {
        $this->builder
            ->analyzer('foo', 'Does Foo')
            ->globalConfig()
                ->booleanNode('switch_foo')->defaultFalse()->end()
            ->end()
            ->perFileConfig()
                ->children()
                    ->scalarNode('some_setting')->end()
                ->end()
            ->end()
        ;
        $tree = $this->builder->getRoot()->getNode(true);

        $processor = new Processor();
        $processed = $processor->process($tree, array($cfg = array(
            'enabled' => true,
            'switch_foo' => true,
            'config' => array(
                'some_setting' => 'bar',
            ),
            'path_configs' => array(
                array(
                    'paths' => array('somepath/*'),
                    'enabled' => false,
                    'config' => array(
                        'some_setting' => 'moo',
                    ),
                )
            ),
        )));
        $cfg['filter'] = array('paths' => array(), 'excluded_paths' => array());

        $this->assertEquals($cfg, $processed);
    }

    protected function setUp()
    {
        $this->builder = new ConfigBuilder();
    }
}