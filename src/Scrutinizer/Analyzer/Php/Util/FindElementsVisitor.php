<?php

namespace Scrutinizer\Analyzer\Php\Util;

use PhpParser\Node;

class FindElementsVisitor extends \PHPParser_NodeVisitorAbstract
{
    private $classes = array();
    private $functions = array();
    private $classNodes = array();
    private $functionNodes = array();

    public function getClasses()
    {
        return $this->classes;
    }

    public function getFunctions()
    {
        return $this->functions;
    }

    public function getResult()
    {
        return new ElementResult($this->classNodes, $this->functionNodes);
    }

    public function enterNode(Node $node)
    {
        if ($node instanceof Node\Stmt\Class_
                || $node instanceof Node\Stmt\Trait_
                || $node instanceof Node\Stmt\Interface_) {
            $className = implode("\\", $node->namespacedName->parts);
            if (false === strpos($className, '\\')) {
                $className = '+global\\'.$className;
            }

            $this->classes[] = $className;
            $this->classNodes[$className] = $node;

            return;
        }

        if ($node instanceof Node\Stmt\Function_) {
            $functionName = implode("\\", $node->namespacedName->parts);
            $this->functions[] = $functionName;
            $this->functionNodes[$functionName] = $node;

            return;
        }
    }

    public function reset()
    {
        $this->classes = $this->functions = array();
    }
}