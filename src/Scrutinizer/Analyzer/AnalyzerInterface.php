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
    public function scrutinize(Project $project);

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
    public function buildConfig(ConfigBuilder $builder);

    /**
     * The name of this analyzer.
     *
     * Should be a lower-case string with "_" as separators.
     *
     * @return string
     */
    public function getName();
}