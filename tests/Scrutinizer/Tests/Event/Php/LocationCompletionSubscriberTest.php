<?php

namespace Scrutinizer\Tests\Event\Php;

use Scrutinizer\Event\Php\LocationCompletionSubscriber;
use Scrutinizer\Event\ProjectEvent;
use Scrutinizer\Model\CodeElement;
use Scrutinizer\Model\Location;
use Scrutinizer\Model\Project;

class LocationCompletionSubscriberTest extends \PHPUnit_Framework_TestCase
{
    /** @var LocationCompletionSubscriber */
    private $subscriber;

    public function testAddsFlags()
    {
        $project = new Project(__DIR__.'/Fixture', array());

        $author = $project->getOrCreateCodeElement('class', 'Scrutinizer\Tests\Event\Php\Fixture\Author');
        $author->setLocation(new Location('Author.php'));

        foreach (array('__construct', 'setFirstname', 'setLastname', 'setFullname', 'getFirstname', 'getLastname', 'getFullname') as $methodName) {
            $method = $project->getOrCreateCodeElement('operation', 'Scrutinizer\Tests\Event\Php\Fixture\Author::'.$methodName);
            $method->setLocation(new Location('Author.php'));
            $author->addChild($method);
        }

        $this->subscriber->onPostAnalysis(new ProjectEvent($project));

        $this->assertEquals(new Location('Author.php', 5, 44), $author->getLocation());

        $expectedFlags = array(
            'Scrutinizer\Tests\Event\Php\Fixture\Author::setFirstname' => array('simple_setter'),
            'Scrutinizer\Tests\Event\Php\Fixture\Author::setLastname' => array('simple_setter'),
            'Scrutinizer\Tests\Event\Php\Fixture\Author::getFirstname' => array('simple_getter'),
            'Scrutinizer\Tests\Event\Php\Fixture\Author::getLastname' => array('simple_getter'),
        );
        foreach ($author->getChildren() as $child) {
            $expected = ! isset($expectedFlags[$child->getName()]) ? array() : $expectedFlags[$child->getName()];
            $this->assertEquals($expected, $child->getFlags(), $child->getName().' has unexpected flags.');
        }
    }

    protected function setUp()
    {
        $this->subscriber = new LocationCompletionSubscriber();
    }
}