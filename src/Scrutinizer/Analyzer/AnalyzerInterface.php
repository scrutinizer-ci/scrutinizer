<?php

namespace Analyzer;

use Symfony\Component\Config\Definition\NodeInterface;

interface AnalyzerInterface
{
    /**
     * Analyzes the given project.
     *
     * @param Project $project
     *
     * @return void
     */
    function scrutinize(Project $project);

    /**
     * Returns the configuration tree of this analyzer.
     *
     * If this analyzer is not configurable, null may be returned.
     *
     * @return NodeInterface|null
     */
    function getConfigTree();
}