<?php

namespace Scrutinizer\Analyzer\Php\Util;

/**
 * Extracts declared classes from PHP files.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class ClassNameExtractor extends \PHPParser_NodeVisitorAbstract
{
    private $classes;
    private $affected = false;

    public function __construct(array $classes)
    {
        $this->classes = $classes;
    }

    public function reset()
    {
        $this->affected = false;
    }

    public function isAffected()
    {
        return $this->affected;
    }

    public function enterNode(\PHPParser_Node $node)
    {
        if ($this->affected) {
            return;
        }

        if ($node instanceof \PHPParser_Node_Name) {
            $className = implode("\\", $node->parts);

            if (in_array($className, $this->classes, true)) {
                $this->affected = true;

                return;
            }
        }
    }
}