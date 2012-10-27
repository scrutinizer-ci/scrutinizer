<?php

namespace Scrutinizer\Analyzer;

use Scrutinizer\Model\Project;
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
     * Returns the config builder which lays out the config structure.
     *
     * This is comparable to Symfony2's default TreeBuilder except that the
     * ConfigBuilder does add a unified way to enable and disable analyzers.
     * You can learn more about how to define the config structure at the URL
     * provided below.
     *
     * @see symfony.com/doc/current/components/config/definition.html
     *
     * @param ConfigBuilder $builder
     *
     * @return void
     */
    function buildConfig(ConfigBuilder $builder);

    /**
     * The name of this analyzer.
     *
     * Should be a lower-case string with "_" as separators.
     *
     * @return string
     */
    function getName();
}