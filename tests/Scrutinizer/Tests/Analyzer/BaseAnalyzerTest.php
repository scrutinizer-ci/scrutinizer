<?php

namespace Scrutinizer\Tests\Analyzer;

use Scrutinizer\Model\CodeElement;
use Scrutinizer\Model\File;
use Scrutinizer\Model\Location;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Finder\Finder;
use Scrutinizer\Scrutinizer;

class BaseAnalyzerTest extends \PHPUnit_Framework_TestCase
{
    private $fs;

    /** @var Scrutinizer */
    private $scrutinizer;

    /**
     * @dataProvider getTestFiles
     */
    public function testScrutinize($filename)
    {
        $testData = $this->parseTestFile($filename);
        $testData['files'][$testData['filename']] = $testData['content'];

        $tmpDir = tempnam(sys_get_temp_dir(), 'scrtnzer');
        $this->fs->remove($tmpDir);
        $this->fs->mkdir($tmpDir);
        foreach ($testData['files'] as $name => $content) {
            $dir = dirname($name);

            if ('' !== $dir && ! is_dir($tmpDir.'/'.$dir)) {
                mkdir($tmpDir.'/'.$dir, 0777, true);
            }

            file_put_contents($tmpDir.'/'.$name, $content);
        }
        file_put_contents($tmpDir.'/.scrutinizer.yml', json_encode($testData['config']));

        $cwd = getcwd();
        chdir($tmpDir);
        $project = $this->scrutinizer->scrutinize($tmpDir, $testData['changed_paths']);
        chdir($cwd);

        /** @var $file File */
        $file = $project->getFile($testData['filename'])->get();
        $comments = $file->getComments();
        $this->fs->remove($tmpDir);

        $this->assertCount(count($testData['comments']), $comments, "Found comments:\n".$this->dumpComments($comments));
        foreach ($testData['comments'] as $line => $lineComments) {
            $this->assertArrayHasKey($line, $comments, 'Expected comments on line '.$line.', but found none. Found comments: '.$this->dumpComments($comments));

            foreach ($lineComments as $comment) {
                foreach ($comments[$line] as $fK => $foundComment) {
                    if (false !== strpos($foundComment, $comment)) {
                        unset($comments[$line][$fK]);

                        continue 2;
                    }
                }

                $this->fail(sprintf("Expected comment '%s' on line %d, but did not find it. Found comments:\n%s", $comment, $line, $this->dumpComments($comments[$line])));
            }

            if (count($comments[$line]) > 0) {
                $this->fail(sprintf("Found some comments on line %d which were not expected. Unexpected comments:\n%s", $line, $this->dumpComments($comments[$line])));
            }
        }

        $fixedFile = $file->getOrCreateFixedFile();
        $this->assertEquals($testData['fixed_content'] ?: $file->getContent(), $fixedFile->getContent());

        $this->assertEquals(
            $this->dumpLineAttributes($testData['line_attributes']),
            $this->dumpLineAttributes($file->getLineAttributes())
        );

        $this->assertEquals(
            $testData['project_metrics'],
            $project->getMetrics()
        );

        $actualElements = $project->getCodeElements();
        foreach ($testData['code_elements'] as $element) {
            /** @var CodeElement $element */

            $found = false;
            foreach ($actualElements as $k => $actualElement) {
                /** @var CodeElement $actualElement */

                if ( ! $element->equals($actualElement)) {
                    continue;
                }

                $found = true;

                unset($actualElements[$k]);
                $this->assertEquals($element->getMetrics(), $actualElement->getMetrics(), 'Metrics for element '.$element);
                $this->assertEquals($element->getLocation(), $actualElement->getLocation(), 'Location for element '.$element);

                $formatChildren = function(CodeElement $element) {
                    $children = array();
                    foreach ($element->getChildren() as $child) {
                        $children[] = (string) $child;
                    }

                    sort($children);

                    return $children;
                };
                $this->assertEquals($formatChildren($element), $formatChildren($actualElement), 'Children for element '.$element);

                break;
            }

            $this->assertTrue($found, 'The code element '.$element.' could not be found in the project, but was expected.');
        }
        $this->assertEmpty($actualElements, "Unexpected Metrics:\n\n- ".implode("\n- ", $actualElements)."\n");
    }

    private function dumpLineAttributes(array $lineAttributes)
    {
        $str = '';
        foreach ($lineAttributes as $line => $attributes) {
            $str .= "Line $line: ".json_encode($attributes, JSON_FORCE_OBJECT)."\n";
        }

        return $str;
    }

    private function dumpComments(array $comments)
    {
        $str = '';
        foreach ($comments as $line => $lineComments) {
            if (is_array($lineComments)) {
                foreach ($lineComments as $comment) {
                    $str .= sprintf("> Line %d: %s\n", $line, $comment);
                }

                continue;
            }

            $str .= "> $lineComments\n";
        }

        return $str;
    }

    public function getTestFiles()
    {
        $tests = array();

        foreach (Finder::create()->in(__DIR__)->name('*.test')->files() as $file) {
            $tests[] = array($file->getRealPath());
        }

        return $tests;
    }

    protected function setUp()
    {
        $this->fs = new Filesystem();
        $this->scrutinizer = new Scrutinizer();
    }

    private function parseTestFile($filename)
    {
        $testContent = file_get_contents($filename);
        $testContent = str_replace('%dir%', __DIR__, $testContent);
        $tokens = preg_split("#\n\n-- (.+?) --\n#", $testContent, null, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);

        $data = array(
            'content' => array_shift($tokens),
            'comments' => array(),
            'config' => array(),
            'files' => array(),
            'project_metrics' => array(),
            'code_elements' => array(),
            'fixed_content' => null,
            'line_attributes' => array(),
            'changed_paths' => array(),
        );
        for ($i=0,$c=count($tokens); $i<$c; $i++) {
            switch ($tokens[$i]) {
                case 'FILENAME':
                    $data['filename'] = $tokens[++$i];
                    continue 2;

                case 'COMMENTS':
                    foreach (explode("\n", $tokens[++$i]) as $line) {
                        if ('' === trim($line)) {
                            continue;
                        }

                        if ( ! preg_match('#^Line ([0-9]+): ([^$]+)$#', $line, $match)) {
                            throw new \RuntimeException(sprintf('Could not extract comment from line: '.$line));
                        }

                        $data['comments'][(integer) $match[1]][] = $match[2];
                    }

                    continue 2;

                case 'CHANGED PATHS':
                case 'CHANGED-PATHS':
                case 'CHANGED_PATHS':
                    foreach (explode("\n", $tokens[++$i]) as $line) {
                        $path = trim($line);
                        if ('' === $path) {
                            continue;
                        }

                        $data['changed_paths'][] = $path;
                    }
                    break;

                case 'LINE ATTRIBUTES':
                case 'LINE-ATTRIBUTES':
                case 'LINE_ATTRIBUTES':
                    foreach (explode("\n", $tokens[++$i]) as $line) {
                        if ('' === trim($line)) {
                            continue;
                        }

                        if ( ! preg_match('#^Line ([0-9]+): ([^$]+)$#', $line, $match)) {
                            throw new \RuntimeException(sprintf('Could not extract attributes for line "%d".', $line));
                        }

                        if (isset($data['line_attributes'][$match[1]])) {
                            throw new \RuntimeException(sprintf('Attributes for line "%d" were specified more than once.', $line));
                        }

                        $attrs = json_decode($match[2], true);
                        if ( ! is_array($attrs)) {
                            throw new \RuntimeException(sprintf('Attributes must be an array, but got %s.', json_encode($attrs)));
                        }

                        $data['line_attributes'][$match[1]] = $attrs;
                    }

                    break;

                case 'FIXED CONTENT':
                case 'FIXED_CONTENT':
                case 'FIXED-CONTENT':
                    $data['fixed_content'] = $tokens[++$i];
                    continue 2;

                case 'PROJECT METRICS':
                case 'PROJECT_METRICS':
                case 'PROJECT-METRICS':
                    $data['project_metrics'] = Yaml::parse($tokens[++$i]);
                    continue 2;

                case 'CODE ELEMENTS':
                case 'CODE-ELEMENTS':
                case 'CODE_ELEMENTS':
                    /** @var CodeElement[] $elements */
                    $elements = array();
                    $relations = array();

                    $getTypeAndName = function($key) {
                        if ( ! preg_match('/^([^(]+)\((.*)\)$/', $key, $match)) {
                            throw new \RuntimeException(sprintf('Could not extract type/name from "%s".', $key));
                        }
                        list(, $type, $name) = $match;

                        return array($type, $name);
                    };

                    foreach (Yaml::parse($tokens[++$i]) as $key => $values) {
                        list($type, $name) = $getTypeAndName($key);
                        $elements[$key] = $element = new CodeElement($type, $name);

                        if (isset($values['metrics'])) {
                            foreach ($values['metrics'] as $k => $v) {
                                $element->setMetric($k, $v);
                            }
                        }

                        if (isset($values['filename'])) {
                            $element->setLocation(new Location($values['filename']));
                        }

                        if (isset($values['location'])) {
                            $element->setLocation(new Location(
                                $values['location']['filename'],
                                isset($values['location']['start_line']) ? $values['location']['start_line'] : null,
                                isset($values['location']['end_line']) ? $values['location']['end_line'] : null
                            ));
                        }

                        if (isset($values['children'])) {
                            $relations[$key] = $values['children'];
                        }
                    }

                    foreach ($relations as $key => $children) {
                        $source = $elements[$key];

                        foreach ($children as $child) {
                            if ( ! isset($elements[$child])) {
                                throw new \RuntimeException(sprintf('The child "%s" does not exist.', $child));
                            }

                            $source->addChild($elements[$child]);
                        }
                    }

                    $data['code_elements'] = array_values($elements);

                    continue 2;

                case 'CONFIG':
                    $data['config'] = Yaml::parse($tokens[++$i]);
                    continue 2;

                default:
                    if (preg_match('#^FILE: (.*)$#', $tokens[$i], $match)) {
                        $data['files'][$match[1]] = $tokens[++$i];
                        break;
                    }

                    throw new \RuntimeException(sprintf('Unknown section header "%s".', $tokens[$i]));
            }
        }

        if ( ! isset($data['filename'])) {
            throw new \RuntimeException('No filename was given.');
        }

        return $data;
    }
}