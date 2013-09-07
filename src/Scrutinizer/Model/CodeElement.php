<?php

namespace Scrutinizer\Model;

use JMS\Serializer\Annotation as Serializer;

class CodeElement
{
    const FLAG_SIMPLE_GETTER = 'simple_getter';
    const FLAG_SIMPLE_SETTER = 'simple_setter';

    private $type;
    private $name;
    private $metrics;

    /**
     * @Serializer\Exclude
     * @var CodeElement[]
     */
    private $children = array();
    private $location;
    private $flags = array();

    public function __construct($type, $name, array $metrics = array())
    {
        $this->type = $type;
        $this->name = $name;
        $this->metrics = $metrics;
    }

    public function getType()
    {
        return $this->type;
    }

    public function getName()
    {
        return $this->name;
    }

    public function addFlag($flag)
    {
        $this->flags[] = $flag;
    }

    public function getFlags()
    {
        return $this->flags;
    }

    public function setMetric($key, $value)
    {
        $this->metrics[$key] = $value;
    }

    public function getChildren()
    {
        return $this->children;
    }

    /**
     * @Serializer\VirtualProperty
     * @Serializer\SerializedName("children")
     */
    public function getFlatChildren()
    {
        $children = array();
        foreach ($this->children as $child) {
            $children[] = array(
                'type' => $child->getType(),
                'name' => $child->getName(),
            );
        }

        return $children;
    }

    public function getMetrics()
    {
        return $this->metrics;
    }

    /**
     * @return Location|null
     */
    public function getLocation()
    {
        return $this->location;
    }

    public function hasLocation()
    {
        return null !== $this->location;
    }

    public function addChild(CodeElement $child)
    {
        foreach ($this->children as $existingChild) {
            if ($existingChild->equals($child)) {
                return;
            }
        }

        $this->children[] = $child;
    }

    public function equals(CodeElement $that)
    {
        return $this->type === $that->type && $this->name === $that->name;
    }

    public function setLocation(Location $location)
    {
        $this->location = $location;
    }

    public function __toString()
    {
        return sprintf('%s(%s)', $this->type, $this->name);
    }
}