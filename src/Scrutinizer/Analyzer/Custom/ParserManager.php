<?php

namespace Scrutinizer\Analyzer\Custom;

use Scrutinizer\Model\Project;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

class ParserManager
{
    private $parsers = array();

    public function __construct()
    {
        foreach (Finder::create()->files()->in(__DIR__.'/Parser')->name('*Parser.php') as $file) {
            /** @var $file SplFileInfo */

            $className = 'Scrutinizer\Analyzer\Custom\Parser\\'.$file->getBasename('.php');
            $this->add(new $className);
        }
    }

    public function add(ParserInterface $parser)
    {
        $this->parsers[$parser->getFormat()] = $parser;
    }

    public function parse(Project $project, $format, $output)
    {
        if ( ! isset($this->parsers[$format])) {
            throw new \InvalidArgumentException(sprintf('The format "%s" is not supported.', $format));
        }

        return $this->parsers[$format]->parse($project, $output);
    }

    public function getSupportedFormats()
    {
        return array_keys($this->parsers);
    }
}