<?php

namespace Scrutinizer\Tests\Event\Php\Fixture;

class Author
{
    private $firstname;
    private $lastname;

    public function __construct($firstname)
    {
        $this->firstname = $firstname;
    }

    public function setFirstname($name)
    {
        $this->firstname = $name;
    }

    public function setLastname($name)
    {
        $this->lastname = $name;
    }

    public function getFirstname()
    {
        return $this->firstname;
    }

    public function getLastname()
    {
        return $this->lastname;
    }

    public function setFullname($name)
    {
        list($this->firstname, $this->lastname) = explode(" ", $name);
    }

    public function getFullname()
    {
        return $this->firstname.' '.$this->lastname;
    }
}