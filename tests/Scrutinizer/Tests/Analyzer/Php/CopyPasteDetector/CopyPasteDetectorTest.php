<?php

namespace Scrutinizer\Tests\Analyzer\Php\CopyPasteDetector;

use Scrutinizer\Analyzer\Php\CopyPasteDetectorAnalyzer;
use Scrutinizer\Model\File;
use Scrutinizer\Model\Project;
use Psr\Log\NullLogger;

class CopyPasteDetectorTest extends \PHPUNit_Framework_TestCase
{
    public function testDetectMultipleCopies()
    {
        $project = new Project(__DIR__.'/Fixture/ProjectWithMultipleClones', array(
            'tools' => array(
                'php_cpd' => array(
                    'enabled' => true,
                    'min_lines' => 3,
                    'min_tokens' => 70,
                    'names' => array('*.php'),
                    'excluded_dirs' => array(),
                    'filter' => array(),
                )
            )
        ));

        $this->runAnalyzer($project);

        /** @var File $category */
        $category = $project->getFile('category.php')->get();
        $this->assertEquals(
            array (
                24 =>
                    array (
                        'duplication' =>
                            array (
                                'lines' => 39,
                                'locations' =>
                                    array (
                                        0 =>
                                            array (
                                                'path' => 'category.php',
                                                'line' => 24,
                                            ),
                                        1 =>
                                            array (
                                                'path' => 'file.php',
                                                'line' => 18,
                                            ),
                                    ),
                            ),
                    ),
            ),
            $category->getLineAttributes()
        );

        /** @var File $file */
        $file = $project->getFile('file.php')->get();
        $this->assertEquals(
            array (
                16 =>
                    array (
                        'duplication' =>
                            array (
                                'lines' => 41,
                                'locations' =>
                                    array (
                                        0 =>
                                            array (
                                                'path' => 'file.php',
                                                'line' => 16,
                                            ),
                                        1 =>
                                            array (
                                                'path' => 'media.php',
                                                'line' => 25,
                                            ),
                                    ),
                            ),
                    ),
                18 => array(
                    'duplication' => array(
                        'lines' => 39,
                        'locations' => array(
                            array('path' => 'category.php', 'line' => 24),
                            array('path' => 'file.php', 'line' => 18),
                        )
                    )
                )
            ),
            $file->getLineAttributes()
        );

        /** @var File $media */
        $media = $project->getFile('media.php')->get();
        $this->assertEquals(
            array (
                25 =>
                    array (
                        'duplication' =>
                            array (
                                'lines' => 41,
                                'locations' =>
                                    array (
                                        0 =>
                                            array (
                                                'path' => 'file.php',
                                                'line' => 16,
                                            ),
                                        1 =>
                                            array (
                                                'path' => 'media.php',
                                                'line' => 25,
                                            ),
                                    ),
                            ),
                    ),
            ),
            $media->getLineAttributes()
        );
    }

    private function runAnalyzer(Project $project)
    {
        $project->setAnalyzerName('php_cpd');
        $analyzer = new CopyPasteDetectorAnalyzer();
        $analyzer->setLogger(new NullLogger());
        $analyzer->scrutinize($project);
    }
}