<?php

namespace Scrutinizer\Model;

class Comment
{
    private $id;
    private $message;
    private $params;

    public function __construct($id, $message, array $params = array())
    {
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

    public function __toString()
    {
        $replaceMap = array();
        foreach ($this->params as $k => $v) {
            $replaceMap['%'.$k.'%'] = $v;
        }

        return strtr($this->message, $replaceMap);
    }
}