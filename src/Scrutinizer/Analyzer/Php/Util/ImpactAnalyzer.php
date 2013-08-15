<?php

namespace Scrutinizer\Analyzer\Php\Util;

use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

class ImpactAnalyzer
{
    /**
     * Finds all PHP files which might be affected by a change to the given files.
     *
     * @param array $changedFiles
     *
     * @return string[]
     */
    public function findAffectedFiles($dir, array $changedFiles)
    {
        if ( ! is_dir($dir)) {
            throw new \InvalidArgumentException(sprintf('The directory "%s" does not exist.', $dir));
        }

        $phpFiles = $this->parsePhpFiles($dir);
        $classes = $this->findChangedElements($dir, $changedFiles, $phpFiles);

        $affectedFiles = array();
        do {
            $beforeAffectedFiles = $affectedFiles;
            $traverser = new \PHPParser_NodeTraverser();
            $traverser->addVisitor($finder = new FindElementsVisitor());
            $traverser->addVisitor($usageVisitor = new UsageVisitor($classes));

            foreach ($phpFiles as $pathname => $ast) {
                $traverser->traverse($ast);

                if ($usageVisitor->isAffected()) {
                    unset($phpFiles[$pathname]);
                    $affectedFiles[] = $pathname;
                    $classes = array_merge($classes, $finder->getClasses());
                }

                $finder->reset();
                $usageVisitor->reset();
            }

        } while ($beforeAffectedFiles !== $affectedFiles);

        return $affectedFiles;
    }

    private function parsePhpFiles($dir)
    {
        $resolver = new \PHPParser_NodeTraverser();
        $resolver->addVisitor(new \PHPParser_NodeVisitor_NameResolver());

        $filter = function(SplFileInfo $file) {
            $h = fopen($file->getRealPath(), 'r');

            return '<?php' === fread($h, 5);
        };

        $phpFiles = array();
        foreach (Finder::create()->in($dir)->files()->filter($filter) as $file) {
            /** @var $file SplFileInfo */

            $parser = new \PHPParser_Parser(new \PHPParser_Lexer());
            $phpFiles[$file->getRelativePathname()] = $resolver->traverse($parser->parse($file->getContents()));
        }

        return $phpFiles;
    }

    private function findChangedElements($dir, array $changedFiles, array &$phpFiles)
    {
        $classes = array();
        $resolver = new \PHPParser_NodeTraverser();
        $resolver->addVisitor(new \PHPParser_NodeVisitor_NameResolver());

        $finder = new \PHPParser_NodeTraverser();
        $finder->addVisitor($visitor = new FindElementsVisitor());

        foreach ($changedFiles as $pathname) {
            // Not a PHP file.
            if ( ! isset($phpFiles[$pathname])) {
                continue;
            }

            $finder->traverse($phpFiles[$pathname]);
            $classes = array_merge($classes, $visitor->getClasses());
            $visitor->reset();

            // We also automatically remove all files which we have already analyzed.
            unset($phpFiles[$pathname]);
        }

        return array_unique($classes);
    }
}