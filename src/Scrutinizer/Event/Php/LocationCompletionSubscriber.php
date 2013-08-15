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
                $nodes = $this->getElementNodes($file);

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