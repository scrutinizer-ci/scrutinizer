<?php

namespace Scrutinizer\Analyzer\Php\Util;

use PhpParser\Node;

/**
 * Checks whether one of the given classes is used in the test.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class UsageVisitor extends \PHPParser_NodeVisitorAbstract
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

    public function enterNode(Node $node)
    {
        if ($this->affected) {
            return;
        }

        if ($node instanceof Node\Name) {
            $className = implode("\\", $node->parts);

            if (in_array($className, $this->classes, true)) {
                $this->affected = true;

                return;
            }
        }
    }
}