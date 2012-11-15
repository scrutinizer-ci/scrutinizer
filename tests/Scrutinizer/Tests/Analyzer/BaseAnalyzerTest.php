<?php

namespace Scrutinizer\Tests\Analyzer;

use Scrutinizer\Model\File;
use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Finder\Finder;
use Scrutinizer\Scrutinizer;

class BaseAnalyzerTest extends \PHPUnit_Framework_TestCase
{
    private $scrutinizer;

    /**
     * @dataProvider getTestFiles
     */
    public function testScrutinize($filename)
    {
        $testData = $this->parseTestFile($filename);

        $testData['files'][$testData['filename']] = $file = new File($testData['filename'], $testData['content']);
        $this->scrutinizer->scrutinizeFiles(
            $testData['files'],
            $testData['config']
        );

        $comments = $file->getComments();
        $this->assertCount(count($testData['comments']), $comments, "Found comments:\n".$this->dumpComments($comments));
        foreach ($testData['comments'] as $line => $lineComments) {
            $this->assertArrayHasKey($line, $comments, 'Expected comments on line '.$line.', but found none. Found comments: '.$this->dumpComments($comments));

            foreach ($lineComments as $k => $comment) {
                foreach ($comments[$line] as $fK => $foundComment) {
                    if ($comment === (string) $foundComment) {
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
        $this->scrutinizer = new Scrutinizer();
    }

    private function parseTestFile($filename)
    {
        $tokens = preg_split("#\n\n-- (.+?) --\n#", file_get_contents($filename), null, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);

        $data = array('content' => array_shift($tokens), 'comments' => array(), 'config' => array(), 'files' => array());
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

                case 'CONFIG':
                    $data['config'] = Yaml::parse($tokens[++$i]);
                    continue 2;

                default:
                    if (preg_match('#^FILE: (.*)$#', $tokens[$i], $match)) {
                        $data['files'][$match[1]] = new File($match[1], $tokens[++$i]);
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