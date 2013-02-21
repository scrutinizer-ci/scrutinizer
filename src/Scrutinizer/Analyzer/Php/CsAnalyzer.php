<?php

namespace Scrutinizer\Analyzer\Php;

use Scrutinizer\Analyzer\AbstractFileAnalyzer;
use Scrutinizer\Analyzer\Parser\CheckstyleParser;
use Scrutinizer\Config\ConfigBuilder;
use Scrutinizer\Model\Comment;
use Scrutinizer\Model\File;
use Scrutinizer\Model\Project;
use Scrutinizer\Util\XmlUtils;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

/**
 * Runs the PHP Code Sniffer.
 *
 * @display-name PHP Code Sniffer
 * @doc-path tools/php/code-sniffer/
 */
class CsAnalyzer extends AbstractFileAnalyzer
{
    public function getInfo()
    {
        return 'Runs PHP Code Sniffer';
    }

    public function getName()
    {
        return 'php_code_sniffer';
    }

    public function getDefaultExtensions()
    {
        return array('php');
    }

    public function buildConfigInternal(ConfigBuilder $builder)
    {
        $builder
            ->globalConfig()
                ->scalarNode('command')
                    ->defaultValue('phpcs')
                ->end()
            ->end()
            ->perFileConfig()
                ->addDefaultsIfNotSet()
                ->children()
                    ->scalarNode('standard')
                        ->info('Built-in standards: PEAR, PHPCS, PSR1, PSR2, Squiz, Zend')
                        ->defaultValue('PSR1')
                    ->end()
                    ->arrayNode('sniffs')
                        ->prototype('scalar')->end()
                    ->end()
                    ->scalarNode('severity')->end()
                    ->scalarNode('error_severity')->end()
                    ->scalarNode('warning_severity')->end()
                    ->scalarNode('tab_width')->end()
                    ->scalarNode('encoding')->end()
                    ->arrayNode('config')
                        ->useAttributeAsKey('key')
                        ->prototype('scalar')->end()
                    ->end()
                ->end()
            ->end()
        ;
    }

    public function analyze(Project $project, File $file)
    {
        $config = $project->getFileConfig($file);
        $cmd = $project->getGlobalConfig('command');
        $cmd .= ' --standard='.escapeshellarg($config['standard']);

        if ( ! empty($config['sniffs'])) {
            $cmd .= ' --sniffs='.implode(',', $config['sniffs']);
        }
        if ( ! empty($config['severity'])) {
            $cmd .= ' --severity='.$config['severity'];
        }
        if ( ! empty($config['error_severity'])) {
            $cmd .= ' --error-severity='.$config['error_severity'];
        }
        if ( ! empty($config['warning_severity'])) {
            $cmd .= ' --warning-severity='.$config['warning_severity'];
        }
        if ( ! empty($config['tab_width'])) {
            $cmd .= ' --tab-width='.$config['tab_width'];
        }
        if ( ! empty($config['encoding'])) {
            $cmd .= ' --encoding='.$config['encoding'];
        }

        foreach ($config['config'] as $k => $v) {
            $cmd .= ' --config '.escapeshellarg($k).' '.escapeshellarg($v);
        }

        $outputFile = tempnam(sys_get_temp_dir(), 'phpcs');
        $cmd .= ' --report-checkstyle='.escapeshellarg($outputFile);

        $inputFile = tempnam(sys_get_temp_dir(), 'phpcs');
        file_put_contents($inputFile, $file->getContent());
        $cmd .= ' '.escapeshellarg($inputFile);

        $proc = new Process($cmd, $project->getDir());
        $proc->run();

        $result = file_get_contents($outputFile);

        unlink($outputFile);
        unlink($inputFile);

        if ($proc->getExitCode() > 1) {
            throw new ProcessFailedException($proc);
        }

        /*
                <?xml version="1.0" encoding="UTF-8"?>
                <checkstyle version="1.0.0">
                    <file name="/path/to/code/myfile.php">
                        <error line="2" column="1" severity="error" message="Missing file doc comment" source="PEAR.Commenting.FileComment"/>
                        <error line="20" column="43" severity="error" message="PHP keywords must be lowercase; expected &quot;false&quot; but found &quot;FALSE&quot;" source="Generic.PHP.LowerCaseConstant"/>
                        <error line="47" column="1" severity="error" message="Line not indented correctly; expected 4 spaces but found 1" source="PEAR.WhiteSpace.ScopeIndent"/>
                        <error line="47" column="20" severity="warning" message="Equals sign not aligned with surrounding assignments" source="Generic.Formatting.MultipleStatementAlignment"/>
                        <error line="51" column="4" severity="error" message="Missing function doc comment" source="PEAR.Commenting.FunctionComment"/>
                    </file>
                </checkstyle>
        */

        $doc = XmlUtils::safeParse($result);
        foreach ($doc->xpath('//error') as $errorElem) {
            /** @var $errorElem \SimpleXMLElement */

            $file->addComment((integer) $errorElem->attributes()->line, new Comment(
                'php_code_sniffer',
                (string) $errorElem->attributes()->source,
                html_entity_decode((string) $errorElem->attributes()->message, ENT_QUOTES, 'UTF-8')
            ));
        }
    }
}
