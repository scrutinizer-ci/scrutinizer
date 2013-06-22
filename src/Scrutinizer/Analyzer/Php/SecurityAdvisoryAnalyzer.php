<?php

namespace Scrutinizer\Analyzer\Php;

use Guzzle\Http\Client;
use Guzzle\Plugin\Backoff\BackoffPlugin;
use Scrutinizer\Analyzer\AnalyzerInterface;
use Scrutinizer\Config\ConfigBuilder;
use Scrutinizer\Model\File;
use Scrutinizer\Model\Project;
use Scrutinizer\Model\Comment;

/**
 * @display-name Security Advisory Checker
 * @doc-path tools/php/security-advisory-checker/
 */
class SecurityAdvisoryAnalyzer implements AnalyzerInterface
{
    private $client;

    public function __construct()
    {
        $this->client = new Client('https://security.sensiolabs.org');
        $this->client->addSubscriber(BackoffPlugin::getExponentialBackoff());
    }

    /**
     * Analyzes the given project.
     *
     * @param Project $project
     *
     * @return void
     */
    public function scrutinize(Project $project)
    {
        if ( ! $project->isAnalyzed('composer.lock')) {
            return;
        }

        $project->getFile('composer.lock')
            ->map(function(File $file) {
                $data = $this->retrieveAdvisories($file);

                $content = $file->getContent();
                $getLine = function($packageName) use ($content) {
                    if (false === $pos = strpos($content, '"name": "'.$packageName.'"')) {
                        return 1;
                    }

                    return 1 + substr_count($content, "\n", 0, $pos);
                };

                foreach ($data as $packageName => $packageData) {
                    $line = $getLine($packageName);

                    switch (count($packageData['advisories'])) {
                        case 0:
                            break;

                        case 1:
                            $file->addComment($line, new Comment(
                                'sensiolabs_security_checker',
                                'sensiolabs_security_checker.advisory',
                                "There is a security advisory for your installed version of {package}:\n\n[{title}]({link})",
                                array(
                                    'package' => $packageName,
                                    'title' => reset($packageData['advisories'])['title'],
                                    'link' => reset($packageData['advisories'])['link'],
                                )
                            ));
                            break;

                        default:
                            $message = "There are {count} security advisories for your installed version of {package}:\n\n";

                            foreach ($packageData['advisories'] as $advisory) {
                                $message .= "- [{$advisory['title']}]({$advisory['link']})\n";
                            }

                            $file->addComment($line, new Comment(
                                'sensiolabs_security_checker',
                                'sensiolabs_security_checker.advisories',
                                $message,
                                array('package' => $packageName, 'count' => count($packageData['advisories']))
                            ));
                            break;

                    }
                }
            })
        ;
    }

    /**
     * Builds the configuration structure of this analyzer.
     *
     * This is comparable to Symfony2's default builders except that the
     * ConfigBuilder does add a unified way to enable and disable analyzers,
     * and also provides a unified basic structure for all analyzers.
     *
     * You can read more about how to define your configuration at
     * http://symfony.com/doc/current/components/config/definition.html
     *
     * @param ConfigBuilder $builder
     *
     * @return void
     */
    public function buildConfig(ConfigBuilder $builder)
    {
        $builder->info('Checks your dependencies against sensiolabs\'s security advisory database.');
    }

    /**
     * The name of this analyzer.
     *
     * Should be a lower-case string with "_" as separators.
     *
     * @return string
     */
    public function getName()
    {
        return 'sensiolabs_security_checker';
    }

    private function retrieveAdvisories(File $file)
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'sec-check');
        file_put_contents($tmpFile, $file->getContent());

        try {
            $response = $this->client->post(
                '/check_lock',
                array('Accept' => 'application/json'),
                array('lock' => '@'.$tmpFile)
            )->send();
        } catch (\Exception $ex) {
            unlink($tmpFile);

            throw $ex;
        }

        unlink($tmpFile);

        $data = json_decode(
            $response->getBody(true),
            true
        );

        switch ($response->getStatusCode()) {
            case 200:
                if ( ! is_array($data)) {
                    throw new \RuntimeException(sprintf('The web service did not return valid JSON, but got "%s".', $response->getBody(true)));
                }
                break;

            case 400:
                throw new \RuntimeException(isset($data['error']) ? $data['error'] : 'The web service returned a 400 error code.');

            default:
                throw new \RuntimeException(sprintf('The web service failed for an unknown reason (Response Code %d).', $response->getStatusCode()));
        }

        return $data;
    }
}