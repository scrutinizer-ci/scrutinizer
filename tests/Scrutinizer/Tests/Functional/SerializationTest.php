<?php

namespace Scrutinizer\Tests\Functional;

use JMS\Serializer\JsonSerializationVisitor;
use JMS\Serializer\Naming\CamelCaseNamingStrategy;
use JMS\Serializer\Naming\SerializedNameAnnotationStrategy;
use JMS\Serializer\SerializerBuilder;
use Scrutinizer\Model\Project;

class SerializationTest extends \PHPUnit_Framework_TestCase
{
    private $serializer;

    public function testProjectWithLineAttributes()
    {
        $project = new Project(__DIR__, array());
        $file = $project->getFile(basename(__FILE__))->get();
        $file->setLineAttribute(1, 'test', 'foo');

        $package = $project->getOrCreateCodeElement('package', 'Foo');
        $package->setMetric('a', 5);

        $class = $project->getOrCreateCodeElement('class', 'Foo\\Bar');
        $class->setMetric('b', 10);
        $package->addChild($class);

        $this->assertEquals(
            '{
    "files": [
        {
            "path": "SerializationTest.php",
            "comments": [

            ],
            "metrics": [

            ],
            "line_attributes": {
                "1": {
                    "test": "foo"
                }
            }
        }
    ],
    "metrics": [

    ],
    "code_elements": [
        {
            "children": [
                {
                    "type": "class",
                    "name": "Foo\\\\Bar"
                }
            ],
            "type": "package",
            "name": "Foo",
            "metrics": {
                "a": 5
            },
            "flags": [

            ]
        },
        {
            "children": [

            ],
            "type": "class",
            "name": "Foo\\\\Bar",
            "metrics": {
                "b": 10
            },
            "flags": [

            ]
        }
    ]
}',
            $this->serializer->serialize($project, 'json')
        );
    }

    protected function setUp()
    {
        $visitor = new JsonSerializationVisitor(new SerializedNameAnnotationStrategy(new CamelCaseNamingStrategy()));
        $visitor->setOptions(JSON_PRETTY_PRINT);

        $this->serializer = SerializerBuilder::create()
            ->setSerializationVisitor('json', $visitor)
            ->build();
    }
}