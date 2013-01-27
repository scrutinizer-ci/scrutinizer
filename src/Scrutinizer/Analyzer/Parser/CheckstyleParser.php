<?php

namespace Scrutinizer\Analyzer\Parser;

use Scrutinizer\Analyzer\ParserInterface;
use Scrutinizer\Model\Project;
use Scrutinizer\Util\XmlUtils;

class CheckstyleParser implements ParserInterface
{
    public function getFormat()
    {
        return 'checkstyle';
    }

    public function parse($content)
    {
        $doc = XmlUtils::safeParse($content);

        $comments = array();
        foreach ($doc->xpath('//error') as $errorElem) {
            /** @var $errorElem \DOMElement */

            $comments[] = array(
                'line' => (integer) $errorElem->attributes->line,
                'id' => (string) $errorElem->attributes->source,
                'message' => html_entity_decode((string) $errorElem->attributes->message, ENT_QUOTES, 'UTF-8'),
                'params' => array(),
            );
        }

        return array(
            'comments' => $comments,
        );
    }
}