<?php

namespace Scrutinizer\Model;

class Metric
{
    private $key;
    private $label;
    private $type;

    public function __construct($key, $label, $type)
    {
        $this->key = $key;
        $this->label = $label;
        $this->type = $type;
    }

    public function getKey()
    {
        return $this->key;
    }

    public function getLabel()
    {
        return $this->label;
    }

    public function getType()
    {
        return $this->type;
    }
}
