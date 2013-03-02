<?php

namespace Scrutinizer\Analyzer\Php\Util;

class FindElementsVisitor extends \PHPParser_NodeVisitorAbstract
{
    private $classes = array();
    private $functions = array();

    public function getClasses()
    {
        return $this->classes;
    }

    public function getFunctions()
    {
        return $this->functions;
    }

    public function enterNode(\PHPParser_Node $node)
    {
        if ($node instanceof \PHPParser_Node_Stmt_Class
                || $node instanceof \PHPParser_Node_Stmt_Trait
                || $node instanceof \PHPParser_Node_Stmt_Interface) {
            $this->classes[] = implode("\\", $node->namespacedName->parts);

            return;
        }

        if ($node instanceof \PHPParser_Node_Stmt_Function) {
            $this->functions[] = implode("\\", $node->namespacedName->parts);

            return;
        }
    }

    public function reset()
    {
        $this->classes = $this->functions = array();
    }
}