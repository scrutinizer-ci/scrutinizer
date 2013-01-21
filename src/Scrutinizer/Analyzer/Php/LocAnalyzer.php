<?php

namespace Scrutinizer\Analyzer\Php;

use Scrutinizer\Util\XmlUtils;
use Scrutinizer\Model\Metric;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;
use Scrutinizer\Model\File;
use Scrutinizer\Model\Project;
use Scrutinizer\Analyzer\AbstractFileAnalyzer;

/**
 * Integrates LOC Analyzer
 *
 * @doc-path tools/php/loc/
 * @display-name PHP Loc
 */
class LocAnalyzer extends AbstractFileAnalyzer
{
    public function getName()
    {
        return 'phploc';
    }

    public function getMetrics()
    {
        return array(
        );
    }

    public function analyze(Project $project, File $file)
    {
        $inputFile = $this->fs->createTempFile($file->getContent());
        $outFile = $this->fs->createTempFile();

        $proc = new Process('phploc --log-xml '.escapeshellarg($outFile->getName()).' '.escapeshellarg($inputFile->getName()));
        $executedProc = $this->executor->execute($proc);

        $output = $outFile->reread();
        $inputFile->delete();
        $outFile->delete();

        if (0 !== $executedProc->getExitCode()) {
            throw new ProcessFailedException($executedProc);
        }

        /*
         * <?xml version="1.0" encoding="UTF-8"?>
            <phploc>
              <loc>107</loc>
              <nclocClasses>85</nclocClasses>
              <cloc>7</cloc>
              <ncloc>100</ncloc>
              <ccn>11</ccn>
              <ccnMethods>11</ccnMethods>
              <interfaces>0</interfaces>
              <traits>0</traits>
              <classes>1</classes>
              <abstractClasses>0</abstractClasses>
              <concreteClasses>1</concreteClasses>
              <anonymousFunctions>0</anonymousFunctions>
              <functions>0</functions>
              <methods>6</methods>
              <publicMethods>5</publicMethods>
              <nonPublicMethods>1</nonPublicMethods>
              <nonStaticMethods>6</nonStaticMethods>
              <staticMethods>0</staticMethods>
              <constants>0</constants>
              <classConstants>0</classConstants>
              <globalConstants>0</globalConstants>
              <ccnByLoc>0.11</ccnByLoc>
              <ccnByNom>2.8333333333333</ccnByNom>
              <nclocByNoc>85</nclocByNoc>
              <nclocByNom>14.166666666667</nclocByNom>
              <namespaces>1</namespaces>
            </phploc>
         */
        $doc = XmlUtils::safeParse($output);
        $file
            ->measure('loc', (integer) $doc->loc)
            ->measure('nclocClasses', (integer) $doc->nclocClasses)
            ->measure('cloc', (integer) $doc->cloc)
            ->measure('ncloc', (integer) $doc->ncloc)
            ->measure('ccn', (integer) $doc->ccn)
            ->measure('ccnMethods', (integer) $doc->ccnMethods)
            ->measure('interfaces', (integer) $doc->interfaces)
            ->measure('traits', (integer) $doc->traits)
            ->measure('classes', (integer) $doc->classes)
            ->measure('abstractClasses', (integer) $doc->abstractClasses)
            ->measure('concreteClasses', (integer) $doc->concreteClasses)
            ->measure('anonymousFunctions', (integer) $doc->anonymousFunctions)
            ->measure('functions', (integer) $doc->functions)
            ->measure('methods', (integer) $doc->methods)
            ->measure('publicMethods', (integer) $doc->publicMethods)
            ->measure('nonPublicMethods', (integer) $doc->nonPublicMethods)
            ->measure('nonStaticMethods', (integer) $doc->nonStaticMethods)
            ->measure('staticMethods', (integer) $doc->staticMethods)
            ->measure('constants', (integer) $doc->constants)
            ->measure('classConstants', (integer) $doc->classConstants)
            ->measure('globalConstants', (integer) $doc->globalConstants)
        ;
    }

    protected function getInfo()
    {
        return 'phploc is a tool for quickly measuring the size and analyzing the structure of a PHP project.';
    }

    protected function getDefaultExtensions()
    {
        return array('php');
    }
}
