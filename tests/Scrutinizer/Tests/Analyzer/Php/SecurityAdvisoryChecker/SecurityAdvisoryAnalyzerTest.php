<?php

namespace Scrutinizer\Tests\Analyzer\Php\SecurityAdvisoryChecker;

use Scrutinizer\Analyzer\Php\SecurityAdvisoryAnalyzer;
use Scrutinizer\Model\File;
use Scrutinizer\Model\Project;

class SecurityAdvisoryAnalyzerTest extends \PHPUnit_Framework_TestCase
{
    public function testAnalyzeProjectWithoutComposer()
    {
        $analyzer = new SecurityAdvisoryAnalyzer();

        $project = new Project(__DIR__.'/Fixture/ProjectWithoutComposer', array(
            'tools' => array(
                'sensiolabs_security_checker' => array(
                    'enabled' => true,
                )
            )
        ));

        $analyzer->scrutinize($project);
    }

    public function testAnalyzeUnsafeProject()
    {
        $project = new Project(__DIR__.'/Fixture/UnsafeProject', array(
            'tools' => array(
                'sensiolabs_security_checker' => array(
                    'enabled' => true,
                )
            )
        ));

        (new SecurityAdvisoryAnalyzer())->scrutinize($project);

        /** @var File $file */
        $file = $project->getFile('composer.lock')->get();
        $this->assertGreaterThan(0, count($file->getComments()));
    }

    public function testAnalyzeSafeProject()
    {
        $project = new Project(__DIR__.'/Fixture/SafeProject', array(
            'tools' => array(
                'sensiolabs_security_checker' => array(
                    'enabled' => true,
                )
            )
        ));

        (new SecurityAdvisoryAnalyzer())->scrutinize($project);

        /** @var File $file */
        $file = $project->getFile('composer.lock')->get();
        $this->assertCount(0, $file->getComments());
    }
}