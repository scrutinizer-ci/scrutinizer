<?php

namespace Scrutinizer\Analyzer\Php\Util;

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

    public function enterNode(\PHPParser_Node $node)
    {
        if ($node instanceof \PHPParser_Node_Stmt_Class
                || $node instanceof \PHPParser_Node_Stmt_Trait
                || $node instanceof \PHPParser_Node_Stmt_Interface) {
            $className = implode("\\", $node->namespacedName->parts);
            $this->classes[] = $className;
            $this->classNodes[$className] = $node;

            return;
        }

        if ($node instanceof \PHPParser_Node_Stmt_Function) {
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