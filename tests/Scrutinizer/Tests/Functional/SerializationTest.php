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
    "config": [

    ],
    "metrics": [

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