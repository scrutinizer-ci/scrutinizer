<?php

namespace Scrutinizer\Analyzer;

use Scrutinizer\Config\ConfigBuilder;

/**
 * Interface for analyzers.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
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
     * @return ConfigBuilder
     */
    function getConfigBuilder();
}