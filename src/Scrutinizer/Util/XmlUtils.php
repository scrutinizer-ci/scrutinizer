<?php

namespace Scrutinizer\Util;

abstract class XmlUtils
{
    public static function safeParse($str)
    {
        $previous = libxml_disable_entity_loader(true);
        $doc = simplexml_load_string($str);
        libxml_disable_entity_loader($previous);

        return $doc;
    }

    private final function __construct() { }
}