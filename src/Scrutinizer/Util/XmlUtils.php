<?php

namespace Scrutinizer\Util;

abstract class XmlUtils
{
    /**
     * @param string $str
     *
     * @return \SimpleXMLElement
     */
    public static function safeParse($str)
    {
        libxml_clear_errors();
        $previous = libxml_disable_entity_loader(true);
        $doc = simplexml_load_string($str);
        libxml_disable_entity_loader($previous);

        if (false === $doc) {
            $message = 'Could not parse XML "'.$str.'".';
            if (false !== $error = libxml_get_last_error()) {
                $message .= ' Error Message: '.$error->message;
            }

            throw new \RuntimeException($message);
        }

        return $doc;
    }

    final private function __construct() { }
}