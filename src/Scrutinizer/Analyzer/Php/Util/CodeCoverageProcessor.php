<?php

namespace Scrutinizer\Analyzer\Php\Util;

use JMS\PhpManipulator\TokenStream;
use Scrutinizer\Model\File;
use Scrutinizer\Model\Location;
use Scrutinizer\Model\Project;
use Scrutinizer\Util\PathUtils;
use Scrutinizer\Util\XmlUtils;

class CodeCoverageProcessor
{
    private $tokenStream;

    public function __construct()
    {
        $this->tokenStream = new TokenStream();
    }

    public function processCloverFile(Project $project, $content)
    {
        $doc = XmlUtils::safeParse($content);
        $rootDir = $project->getDir().'/';
        $prefixLength = strlen($rootDir);

        foreach ($doc->xpath('//file') as $xmlFile) {
            if ( ! isset($xmlFile->line)) {
                continue;
            }

            $filename = substr((string) $xmlFile->attributes()->name, $prefixLength);
            $project->getFile($filename)->forAll(
                function(File $modelFile) use ($xmlFile) {
                    foreach ($xmlFile->line as $line) {
                        $attrs = $line->attributes();
                        $modelFile->setLineAttribute((integer) $attrs->num, 'coverage_count', (integer) $attrs->count);
                    }
                }
            );
        }

        $filteredFiles = $filteredLoc = $filteredNcloc = $filteredClasses = $filteredMethods = $filteredCoveredMethods
            = $filteredConditionals = $filteredCoveredConditionals = $filteredStatements = $filteredCoveredStatements
            = $filteredElements = $filteredCoveredElements = 0;

        /**
         *     <package name="Foo">
        <file name="/tmp/scrtnzerI2LxkB/src/Bar.php">
        <class name="Bar" namespace="Foo">
        <metrics methods="2" coveredmethods="1" conditionals="0" coveredconditionals="0" statements="3"
         *              coveredstatements="2" elements="5" coveredelements="3"/>
        </class>
        <line num="9" type="method" name="__construct" crap="1" count="1"/>
        <line num="11" type="stmt" count="1"/>
        <line num="12" type="stmt" count="1"/>
        <line num="14" type="method" name="getName" crap="2" count="0"/>
        <line num="16" type="stmt" count="0"/>
        <metrics loc="17" ncloc="17" classes="1" methods="2" coveredmethods="1" conditionals="0" coveredconditionals="0" statements="3" coveredstatements="2" elements="5" coveredelements="3"/>
        </file>
         */

        $filter = $project->getGlobalConfig('filter');
        foreach ($doc->xpath('//package') as $packageNode) {
            foreach ($packageNode->xpath('./file') as $fileNode) {
                $filename = substr($fileNode->attributes()->name, strlen($project->getDir()) + 1);

                if (PathUtils::isFiltered($filename, $filter)) {
                    $metricsAttrs = $fileNode->metrics->attributes();

                    $filteredFiles += 1;
                    $filteredLoc += (integer) $metricsAttrs->loc;
                    $filteredNcloc += (integer) $metricsAttrs->ncloc;
                    $filteredClasses += (integer) $metricsAttrs->classes;
                    $filteredMethods += (integer) $metricsAttrs->methods;
                    $filteredCoveredMethods += (integer) $metricsAttrs->coveredmethods;
                    $filteredConditionals += (integer) $metricsAttrs->conditionals;
                    $filteredCoveredConditionals += (integer) $metricsAttrs->coveredconditionals;
                    $filteredStatements += (integer) $metricsAttrs->statements;
                    $filteredCoveredStatements += (integer) $metricsAttrs->coveredstatements;
                    $filteredElements += (integer) $metricsAttrs->elements;
                    $filteredCoveredElements += (integer) $metricsAttrs->coveredelements;

                    continue;
                }

                $packageName = (string) $packageNode->attributes()->name;
                $package = $project->getOrCreateCodeElement('package', $packageName);

                $project->getFile($filename)->forAll(function(File $modelFile) use ($packageName, $project, $fileNode, $package, $filename) {
                    $this->tokenStream->setCode($modelFile->getContent());

                    $addedMethods = 0;
                    foreach ($fileNode->xpath('./class') as $classNode) {
                        $className = $packageName.'\\'.$classNode->attributes()->name;

                        $class = $project->getOrCreateCodeElement('class', $className);
                        $package->addChild($class);

                        $location = new Location($filename);
                        $class->setLocation($location);

                        $metricsAttrs = $classNode->metrics->attributes();
                        $methodCount = (integer) $metricsAttrs->methods;
                        $coveredMethodCount = (integer) $metricsAttrs->coveredmethods;
                        $statements = (integer) $metricsAttrs->statements;
                        $coveredStatements = (integer) $metricsAttrs->coveredstatements;
                        $class->setMetric('php_code_coverage.conditionals', (integer) $metricsAttrs->conditionals);
                        $class->setMetric('php_code_coverage.covered_conditionals', (integer) $metricsAttrs->coveredconditionals);
                        $class->setMetric('php_code_coverage.statements', $statements);
                        $class->setMetric('php_code_coverage.covered_statements', $coveredStatements);
                        $class->setMetric('php_code_coverage.elements', (integer) $metricsAttrs->elements);
                        $class->setMetric('php_code_coverage.covered_elements', (integer) $metricsAttrs->coveredelements);
                        $class->setMetric('php_code_coverage.coverage', $statements > 0 ? $coveredStatements / $statements : 1.0);

                        $i = -1;
                        $addedClassMethods = 0;
                        foreach ($fileNode->xpath('./line') as $lineNode) {
                            $lineAttrs = $lineNode->attributes();

                            if ((string) $lineAttrs->type !== 'method') {
                                continue;
                            }

                            // This is a workaround for a bug in CodeCoverage that displays arguments of closures as
                            // methods of the declaring class.
                            $methodName = (string) $lineAttrs->name;
                            $methodToken = $this->tokenStream->next->findNextToken(function(TokenStream\AbstractToken $token) use ($methodName) {
                                if ( ! $token->matches(T_FUNCTION)) {
                                    return false;
                                }

                                return $token->findNextToken('NO_WHITESPACE_OR_COMMENT')->map(function(TokenStream\AbstractToken $token) use ($methodName) {
                                    return $token->matches(T_STRING) && $token->getContent() === $methodName;
                                })->getOrElse(false);
                            });
                            if ( ! $methodToken->isDefined()) {
                                $methodCount -= 1;

                                if ($lineAttrs->count > 0) {
                                    $coveredMethodCount -= 1;
                                }

                                continue;
                            }

                            $i += 1;

                            if ($i < $addedMethods) {
                                continue;
                            }

                            if ($addedClassMethods >= (integer) $metricsAttrs->methods) {
                                break;
                            }

                            $addedClassMethods += 1;
                            $addedMethods += 1;
                            $method = $project->getOrCreateCodeElement('operation', $className.'::'.$methodName);
                            $method->setLocation($location);
                            $class->addChild($method);

                            $method->setMetric('php_code_coverage.change_risk_anti_pattern', (integer) $lineAttrs->crap);
                            $method->setMetric('php_code_coverage.count', (integer) $lineAttrs->count);
                        }

                        $class->setMetric('php_code_coverage.methods', $methodCount);
                        $class->setMetric('php_code_coverage.covered_methods', $coveredMethodCount);
                    }
                });
            }
        }

        // files="3" loc="114" ncloc="114" classes="3" methods="16" coveredmethods="3" conditionals="0" coveredconditionals="0"
        // statements="38" coveredstatements="5" elements="54" coveredelements="8"
        foreach ($doc->xpath('descendant-or-self::project/metrics') as $metricsNode) {
            $metricsAttrs = $metricsNode->attributes();

            $project->setSimpleValuedMetric('php_code_coverage.files', (integer) $metricsAttrs->files - $filteredFiles);
            $project->setSimpleValuedMetric('php_code_coverage.lines_of_code', (integer) $metricsAttrs->loc - $filteredLoc);
            $project->setSimpleValuedMetric('php_code_coverage.non_comment_lines_of_code', (integer) $metricsAttrs->ncloc - $filteredNcloc);
            $project->setSimpleValuedMetric('php_code_coverage.classes', (integer) $metricsAttrs->classes - $filteredClasses);
            $project->setSimpleValuedMetric('php_code_coverage.methods', (integer) $metricsAttrs->methods - $filteredMethods);
            $project->setSimpleValuedMetric('php_code_coverage.covered_methods', (integer) $metricsAttrs->coveredmethods - $filteredCoveredMethods);
            $project->setSimpleValuedMetric('php_code_coverage.conditionals', (integer) $metricsAttrs->conditionals - $filteredConditionals);
            $project->setSimpleValuedMetric('php_code_coverage.covered_conditionals', (integer) $metricsAttrs->coveredconditionals - $filteredCoveredConditionals);
            $project->setSimpleValuedMetric('php_code_coverage.statements', (integer) $metricsAttrs->statements - $filteredStatements);
            $project->setSimpleValuedMetric('php_code_coverage.covered_statements', (integer) $metricsAttrs->coveredstatements - $filteredCoveredStatements);
            $project->setSimpleValuedMetric('php_code_coverage.elements', (integer) $metricsAttrs->elements - $filteredElements);
            $project->setSimpleValuedMetric('php_code_coverage.covered_elements', (integer) $metricsAttrs->coveredelements - $filteredCoveredElements);
        }
    }
}