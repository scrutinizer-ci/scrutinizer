<?php

namespace Scrutinizer\Analyzer;

use Scrutinizer\Model\Project;
use Scrutinizer\Util\PathUtils;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

class ProjectIteratorFactory
{
    public function createFileIterator(Project $project, array $extensions = array())
    {
        $finder = Finder::create()
            ->in($project->getDir())
            ->files()
            ->filter(function (SplFileInfo $file) use ($project, $extensions) {
                if ( ! $project->isAnalyzed($file->getRelativePathname())) {
                    return false;
                }

                if ( PathUtils::isFiltered($file->getRelativePathname(), $project->getGlobalConfig('filter'))) {
                    return false;
                }

                if ($extensions && ! in_array($file->getExtension(), $extensions, true)) {
                    return false;
                }

                if ( ! $project->getPathConfig($file->getRelativePath(), 'enabled', true)) {
                    return false;
                }

                return true;
            })
        ;

        return $finder;
    }
}