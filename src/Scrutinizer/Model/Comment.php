<?php

namespace Scrutinizer\Model;

class Comment
{
    private $id;
    private $message;
    private $params;
    private $tool;

    public function __construct($tool, $id, $message, array $params = array())
    {
        $this->tool = $tool;
        $this->id = $id;
        $this->message = $message;
        $this->params = $params;
    }

    public function getId()
    {
        return $this->id;
    }

    public function getMessage()
    {
        return $this->message;
    }

    public function getParams()
    {
        return $this->params;
    }

    public function getTool()
    {
        return $this->tool;
    }

    public function __toString()
    {
        $replaceMap = array();
        foreach ($this->params as $k => $v) {
            $replaceMap['%'.$k.'%'] = $v;
            $replaceMap['{'.$k.'}'] = $v;
        }

        return strtr($this->message, $replaceMap);
    }
}