<?php

namespace Scrutinizer\Event\Php;

use Scrutinizer\Analyzer\Php\Util\ElementResult;
use Scrutinizer\Analyzer\Php\Util\FindElementsVisitor;
use Scrutinizer\Event\ProjectEvent;
use Scrutinizer\Model\CodeElement;
use Scrutinizer\Model\File;
use Scrutinizer\Model\Location;
use Scrutinizer\Model\Project;
use Scrutinizer\Scrutinizer;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class LocationCompletionSubscriber implements EventSubscriberInterface
{
    /** @var ElementResult[] */
    private $nodeCache = array();

    public static function getSubscribedEvents()
    {
        return array(Scrutinizer::EVENT_POST_ANALYSIS => 'onPostAnalysis');
    }

    public function onPostAnalysis(ProjectEvent $event)
    {
        $project = $event->getProject();
        foreach ($project->getCodeElements() as $element) {
            if ( ! $element->hasLocation()) {
                continue;
            }

            $location = $element->getLocation();
            if (substr($location->getFilename(), -4) !== '.php') {
                continue;
            }

            $project->getFile($location->getFilename())->forAll(function(File $file) use ($element, $location) {
                try {
                    $nodes = $this->getElementNodes($file);
                } catch (\PHPParser_Error $parseError) {
                    return;
                }

                if ($element->getType() === 'class') {
                    $className = $element->getName();

                    if ( ! isset($nodes->classes[$className])) {
                        return;
                    }

                    $this->updateLocation($element, $location, $nodes->classes[$className]);
                } elseif ($element->getType() === 'operation') {
                    if (false !== $pos = strpos($element->getName(), '::')) {
                        list($className, $methodName) = explode('::', $element->getName());

                        if ( ! isset($nodes->classes[$className])) {
                            return;
                        }

                        $node = $nodes->classes[$className];
                        foreach ($node->stmts as $stmt) {
                            if ( ! $stmt instanceof \PHPParser_Node_Stmt_ClassMethod
                                    || $stmt->name !== $methodName) {
                                continue;
                            }

                            $this->updateLocation($element, $location, $stmt);

                            if ($this->isSimpleGetter($stmt)) {
                                $element->addFlag(CodeElement::FLAG_SIMPLE_GETTER);
                            } elseif ($this->isSimpleSetter($stmt)) {
                                $element->addFlag(CodeElement::FLAG_SIMPLE_SETTER);
                            }

                            return;
                        }
                    } else {
                        if ( ! isset($nodes->functions[$element->getName()])) {
                            return;
                        }

                        $this->updateLocation($element, $location, $nodes->functions[$element->getName()]);
                    }
                }
            });
        }
    }

    private function isSimpleGetter(\PHPParser_Node_Stmt_ClassMethod $method)
    {
        if (count($method->params) > 0 || $method->byRef === true || count($method->stmts) !== 1) {
            return false;
        }

        if ( ! $method->stmts[0] instanceof \PHPParser_Node_Stmt_Return) {
            return false;
        }

        $returnExpr = $method->stmts[0]->expr;

        return $this->isSimpleProperty($returnExpr) || $this->isSimpleStaticProperty($returnExpr);
    }

    private function isThis(\PHPParser_Node $node = null)
    {
        return $node instanceof \PHPParser_Node_Expr_Variable && $node->name === 'this';
    }

    private function isSimpleSetter(\PHPParser_Node_Stmt_ClassMethod $method)
    {
        if (count($method->params) !== 1 || $method->byRef === true || count($method->stmts) !== 1
                || $method->name === '__construct') {
            return false;
        }

        if ( ! $method->stmts[0] instanceof \PHPParser_Node_Expr_Assign) {
            return false;
        }

        if ( ! $this->isSimpleVariable($method->stmts[0]->expr)) {
            return false;
        }

        $var = $method->stmts[0]->var;

        return $this->isSimpleProperty($var) || $this->isSimpleStaticProperty($var);
    }

    private function isSimpleStaticProperty(\PHPParser_Node $node = null)
    {
        if ( ! $node instanceof \PHPParser_Node_Expr_StaticPropertyFetch) {
            return false;
        }

        if ( ! $node->class instanceof \PHPParser_Node_Name) {
            return false;
        }

        return is_string($node->name);
    }

    private function isSimpleProperty(\PHPParser_Node $node = null)
    {
        if ( ! $node instanceof \PHPParser_Node_Expr_PropertyFetch) {
            return false;
        }

        if ( ! $this->isThis($node->var)) {
            return false;
        }

        return is_string($node->name);
    }

    private function isSimpleVariable(\PHPParser_Node $node = null)
    {
        return $node instanceof \PHPParser_Node_Expr_Variable && is_string($node->name);
    }

    private function updateLocation(CodeElement $element, Location $location, \PHPParser_NodeAbstract $node)
    {
        $attributes = $node->getAttributes();

        if (isset($attributes['startLine']) && isset($attributes['endLine'])) {
            $element->setLocation(new Location($location->getFilename(), $attributes['startLine'], $attributes['endLine']));
        }
    }

    private function getElementNodes(File $file)
    {
        if (isset($this->nodeCache[$file->getPath()])) {
            return $this->nodeCache[$file->getPath()];
        }

        $parser = new \PHPParser_Parser(new \PHPParser_Lexer());
        $ast = $parser->parse($file->getContent());

        $traverser = new \PHPParser_NodeTraverser();
        $traverser->addVisitor(new \PHPParser_NodeVisitor_NameResolver());
        $traverser->addVisitor($finder = new FindElementsVisitor());
        $traverser->traverse($ast);

        return $this->nodeCache[$file->getPath()] = $finder->getResult();
    }
}