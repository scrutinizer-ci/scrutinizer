<?php

namespace Scrutinizer\Analyzer\Php;

use Scrutinizer\Analyzer\AbstractFileAnalyzer;
use Scrutinizer\Util\XmlUtils;
use Scrutinizer\Model\Comment;
use Scrutinizer\Model\File;
use Scrutinizer\Config\ConfigBuilder;
use Scrutinizer\Analyzer\FileTraversal;
use Scrutinizer\Model\Project;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

/**
 * Integrates PHP Mess Detector.
 *
 * @doc-path tools/php/mess-detector/
 * @display-name PHP Mess Detector
 */
class MessDetectorAnalyzer extends AbstractFileAnalyzer
{
    private $tmpCwdDir;

    public function getName()
    {
        return 'php_mess_detector';
    }

    protected function getInfo()
    {
        return 'Runs the PHP Mess Detector (http://phpmd.org).';
    }

    protected function getDefaultExtensions()
    {
        return array('php');
    }

    protected function buildConfigInternal(ConfigBuilder $builder)
    {
        $rewriteBooleanArray = function($v) {
            if (is_array($v) && ! empty($v) && is_string(reset($v))) {
                return array_combine($v, array_fill(0, count($v), true));
            }

            return $v;
        };

        $builder
            ->globalConfig()
                ->scalarNode('command')
                    ->defaultValue('phpmd')
                ->end()
            ->end()
            ->perFileConfig('array')
                ->addDefaultsIfNotSet()
                ->beforeNormalization()
                    ->always(function($v) {
                        if (is_array($v) && isset($v['rulesets'])) {
                            $config = array(
                                'code_size_rules' => false,
                                'design_rules' => false,
                                'unused_code_rules' => false,
                                'controversial_rules' => false,
                                'naming_rules' => false
                            );

                            foreach ($v['rulesets'] as $ruleSet) {
                                switch ($ruleSet) {
                                    case 'codesize':
                                        $config['code_size_rules'] = array(
                                            'cyclomatic_complexity' => true,
                                            'npath_complexity' => true,
                                            'excessive_method_length' => true,
                                            'excessive_class_length' => true,
                                            'excessive_parameter_list' => true,
                                            'excessive_public_count' => true,
                                            'too_many_fields' => true,
                                            'excessive_class_complexity' => true
                                        );
                                        break;

                                    case 'design':
                                        $config['design_rules'] = array(
                                            'exit_expression' => true,
                                            'eval_expression' => true,
                                            'goto_statement' => true,
                                            'number_of_class_children' => true,
                                            'depth_of_inheritance' => true,
                                            'coupling_between_objects' => true,
                                        );
                                        break;

                                    case 'unusedcode':
                                        $config['unused_code_rules'] = array(
                                            'unused_private_field' => true,
                                            'unused_local_variable' => true,
                                            'unused_private_method' => true,
                                            'unused_formal_parameter' => true,
                                        );
                                        break;

                                    case 'controversial':
                                        $config['controversial_rules'] = array(
                                            'superglobals' => true,
                                            'camel_case_class_name' => true,
                                            'camel_case_property_name' => true,
                                            'camel_case_method_name' => true,
                                            'camel_case_variable_name' => true,
                                        );
                                        break;

                                    case 'naming':
                                        $config['naming_rules'] = array(
                                            'short_variable' => true,
                                            'long_variable' => true,
                                            'short_method' => true,
                                            'constructor_conflict' => true,
                                            'constant_naming' => true,
                                            'boolean_method_name' => true,
                                        );
                                        break;

                                    default:
                                        $config['ruleset'] = $ruleSet;
                                }
                            }

                            return $config;
                        }

                        return $v;
                    })
                ->end()
                ->children()
                    ->scalarNode('ruleset')
                        ->attribute('help_inline', 'Path to a custom ruleset.xml file (relative to your project\'s root folder).')
                        ->defaultNull()
                    ->end()
                    ->arrayNode('code_size_rules')
                        ->addDefaultsIfNotSet()
                        ->canBeUnset()
                        ->attribute('label', 'Code Size Rules')
                        ->attribute('type', 'choice')
                        ->attribute('depends_on', array('ruleset' => ''))
                        ->beforeNormalization()->always($rewriteBooleanArray)->end()
                        ->children()
                            ->booleanNode('cyclomatic_complexity')
                                ->attribute('label', 'Check whether methods exceed the allowed conditional complexity.')
                                ->defaultTrue()
                            ->end()
                            ->booleanNode('npath_complexity')
                                ->attribute('label', 'Check whether methods exceed the allowed path complexity.')
                                ->defaultTrue()
                            ->end()
                            ->booleanNode('excessive_method_length')
                                ->attribute('label', 'Check whether methods exceed the maximum length.')
                                ->defaultTrue()
                            ->end()
                            ->booleanNode('excessive_class_length')
                                ->attribute('label', 'Check whether classes exceed the maximum length.')
                                ->defaultTrue()
                            ->end()
                            ->booleanNode('excessive_parameter_list')
                                ->attribute('label', 'Check whether methods have too many parameters.')
                                ->defaultTrue()
                            ->end()
                            ->booleanNode('excessive_public_count')
                                ->attribute('label', 'Check whether a class has to many public fields/methods.')
                                ->defaultTrue()
                            ->end()
                            ->booleanNode('too_many_fields')
                                ->attribute('label', 'Check whether a class has too many fields.')
                                ->defaultTrue()
                            ->end()
                            ->booleanNode('too_many_methods')
                                ->attribute('label', 'Check whether a class has too many methods.')
                                ->defaultFalse()
                            ->end()
                            ->booleanNode('excessive_class_complexity')
                                ->attribute('label', 'Check whether a class exceeds the allowed class complexity.')
                                ->defaultTrue()
                            ->end()
                        ->end()
                    ->end()
                    ->arrayNode('design_rules')
                        ->addDefaultsIfNotSet()
                        ->canBeUnset()
                        ->attribute('label', 'Design Rules')
                        ->attribute('type', 'choice')
                        ->attribute('depends_on', array('ruleset' => ''))
                        ->beforeNormalization()->always($rewriteBooleanArray)->end()
                        ->children()
                            ->booleanNode('exit_expression')
                                ->attribute('label', 'Check that the exit expression is not used.')
                                ->defaultTrue()
                            ->end()
                            ->booleanNode('eval_expression')
                                ->attribute('label', 'Check that the eval expression is not used.')
                                ->defaultTrue()
                            ->end()
                            ->booleanNode('goto_statement')
                                ->attribute('label', 'Check that the goto statement is not used.')
                                ->defaultTrue()
                            ->end()
                            ->booleanNode('number_of_class_children')
                                ->attribute('label', 'Check that a class does not exceed the maximum of allowed child classes.')
                                ->defaultTrue()
                            ->end()
                            ->booleanNode('depth_of_inheritance')
                                ->attribute('label', 'Check that the inheritance depth does not exceed the allowed maximum.')
                                ->defaultTrue()
                            ->end()
                            ->booleanNode('coupling_between_objects')
                                ->attribute('label', 'Check that the number of a classes\' dependencies does not exceed the allowed maximum.')
                                ->defaultTrue()
                            ->end()
                        ->end()
                    ->end()
                    ->arrayNode('unused_code_rules')
                        ->addDefaultsIfNotSet()
                        ->canBeUnset()
                        ->attribute('label', 'Unused Code Rules')
                        ->attribute('type', 'choice')
                        ->attribute('depends_on', array('ruleset' => ''))
                        ->beforeNormalization()->always($rewriteBooleanArray)->end()
                        ->children()
                            ->booleanNode('unused_private_field')
                                ->attribute('label', 'Check whether there are unused private fields.')
                                ->defaultTrue()
                            ->end()
                            ->booleanNode('unused_local_variable')
                                ->attribute('label', 'Check whether there are unused local variables.')
                                ->defaultTrue()
                            ->end()
                            ->booleanNode('unused_private_method')
                                ->attribute('label', 'Check whether there are unused private methods.')
                                ->defaultTrue()
                            ->end()
                            ->booleanNode('unused_formal_parameter')
                                ->attribute('label', 'Check whether there are unused formal parameters.')
                                ->defaultFalse()
                            ->end()
                        ->end()
                    ->end()
                    ->arrayNode('naming_rules')
                        ->addDefaultsIfNotSet()
                        ->canBeUnset()
                        ->attribute('label', 'Naming Rules')
                        ->attribute('type', 'choice')
                        ->attribute('depends_on', array('ruleset' => ''))
                        ->beforeNormalization()->always($rewriteBooleanArray)->end()
                        ->children()
                            ->booleanNode('short_variable')
                                ->attribute('label', 'Check that variables are not shorter than the defined minimum.')
                                ->defaultFalse()
                            ->end()
                            ->booleanNode('long_variable')
                                ->attribute('label', 'Check that variables are not longer than the defined maximum.')
                                ->defaultFalse()
                            ->end()
                            ->booleanNode('short_method')
                                ->attribute('label', 'Check that methods are not shorter than the defined minimum.')
                                ->defaultFalse()
                            ->end()
                            ->booleanNode('constructor_conflict')
                                ->attribute('label', 'Check that there is no method named like the class.')
                                ->defaultTrue()
                            ->end()
                            ->booleanNode('constant_naming')
                                ->attribute('label', 'Check that constants are all named in UPPER_CASE.')
                                ->defaultTrue()
                            ->end()
                            ->booleanNode('boolean_method_name')
                                ->attribute('label', 'Check that methods which return a boolean start with "is" or "has".')
                                ->defaultFalse()
                            ->end()
                        ->end()
                    ->end()
                    ->arrayNode('controversial_rules')
                        ->addDefaultsIfNotSet()
                        ->canBeUnset()
                        ->attribute('label', 'Controversial Rules')
                        ->attribute('type', 'choice')
                        ->attribute('depends_on', array('ruleset' => ''))
                        ->beforeNormalization()->always($rewriteBooleanArray)->end()
                        ->children()
                            ->booleanNode('superglobals')
                                ->attribute('label', 'Check that PHP super globals are not accessed directly.')
                                ->defaultTrue()
                            ->end()
                            ->booleanNode('camel_case_class_name')
                                ->attribute('label', 'Check that classes are named in CamelCase.')
                                ->defaultFalse()
                            ->end()
                            ->booleanNode('camel_case_property_name')
                                ->attribute('label', 'Check that properties are named in camelCase.')
                                ->defaultFalse()
                            ->end()
                            ->booleanNode('camel_case_method_name')
                                ->attribute('label', 'Check that methods are named in camelCase.')
                                ->defaultFalse()
                            ->end()
                            ->booleanNode('camel_case_parameter_name')
                                ->attribute('label', 'Check that parameters are named in camelCase.')
                                ->defaultFalse()
                            ->end()
                            ->booleanNode('camel_case_variable_name')
                                ->attribute('label', 'Check that variables are named in camelCase.')
                                ->defaultFalse()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;
    }

    public function scrutinize(Project $project)
    {
        // We temporarily switch the working directory to workaround a bug in PHPMD which causes weird configuration
        // errors, see https://github.com/phpmd/phpmd/issues/47
        $previousCwd = $this->useEmptyWorkingDir();

        try {
            parent::scrutinize($project);
            $this->restoreWorkingDir($previousCwd);
        } catch (\Exception $ex) {
            $this->restoreWorkingDir($previousCwd);

            throw $ex;
        }
    }

    private function restoreWorkingDir($dir)
    {
        chdir($dir);
        rmdir($this->tmpCwdDir);
    }

    private function useEmptyWorkingDir()
    {
        $this->tmpCwdDir = tempnam(sys_get_temp_dir(), 'phpmd_tmp');
        unlink($this->tmpCwdDir);
        if (false === @mkdir($this->tmpCwdDir, 0777, true)) {
            throw new \LogicException('Could not create temporary directory.');
        }

        $cwd = getcwd();
        chdir($this->tmpCwdDir);

        return $cwd;
    }

    public function analyze(Project $project, File $file)
    {
        $command = $project->getGlobalConfig('command');

        $rulesetFile = tempnam(sys_get_temp_dir(), 'phpmd-ruleset');
        $this->createRulesetFile($project, $file, $rulesetFile);

        $inputFile = tempnam(sys_get_temp_dir(), 'phpmd_input');
        file_put_contents($inputFile, $file->getContent());

        $proc = new Process($command.' '.escapeshellarg($inputFile).' xml '.escapeshellarg($rulesetFile), $this->tmpCwdDir);
        $proc->setTimeout(300);
        $exitCode = $proc->run();

        if (0 !== $exitCode && 2 !== $exitCode) {
            throw new ProcessFailedException($proc);
        }

        unlink($inputFile);
        unlink($rulesetFile);

        $output = $proc->getOutput();
        $output = str_replace($inputFile, $file->getPath(), $output);
        $doc = XmlUtils::safeParse($output);

        // <error filename="syntax_error.php" msg="Unexpected end of token stream in file: syntax_error.php." />
        foreach ($doc->xpath('//error') as $error) {
            /** @var $error \SimpleXMLElement */

            $attrs = $error->attributes();
            $file->addComment(1, new Comment($this->getName(), 'php_md.error', (string) $attrs->msg));
        }

        // <violation beginline="4" endline="30" rule="CyclomaticComplexity" ruleset="Code Size Rules"
        //            package="+global" externalInfoUrl="http://phpmd.org/rules/codesize.html#cyclomaticcomplexity"
        //            class="Foo" method="example" priority="3"
        // >The method example() has a Cyclomatic Complexity of 11. The configured cyclomatic complexity threshold is 10.</violation>
        foreach ($doc->xpath('//violation') as $violation) {
            /** @var $violation \SimpleXMLElement */

            $attrs = $violation->attributes();
            $rule = preg_replace_callback('#[A-Z]#', function($v) { return '_'.strtolower($v[0]); }, lcfirst((string) $attrs->rule));
            $file->addComment((integer) $attrs->beginline, new Comment($this->getName(), 'php_md.'.$rule, trim((string) $violation)));
        }
    }

    private function createRulesetFile(Project $project, File $file, $rulesetFile)
    {
        if (null !== $ruleset = $project->getFileConfig($file, 'ruleset')) {
            file_put_contents($rulesetFile, $project->getFile($ruleset)->map(function(File $file) { return $file->getContent(); })->get());

            return;
        }

        $rulesetTemplate = <<<'TEMPLATE'
<?xml version="1.0"?>
<ruleset name="Scrutinizer Ruleset" xmlns="http://pmd.sf.net/ruleset/1.0.0"
                                    xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
                                    xsi:schemaLocation="http://pmd.sf.net/ruleset/1.0.0 http://pmd.sf.net/ruleset_xml_schema.xsd"
                                    xsi:noNamespaceSchemaLocation=" http://pmd.sf.net/ruleset_xml_schema.xsd">
    <description>Auto-generated Ruleset</description>

%s
</ruleset>
TEMPLATE;

        $rules = array();
        foreach ($project->getFileConfig($file) as $k => $flags) {
            if ($k === 'ruleset') {
                continue;
            }

            foreach ($flags as $flag => $on) {
                if ($on !== true) {
                    continue;
                }

                $rules[] = '    <rule ref="'.$this->getBuiltInRulesetFile($k).'/'.$this->getBuiltInRule($flag).'" />';
            }
        }

        $ruleset = sprintf($rulesetTemplate, implode("\n", $rules));
        file_put_contents($rulesetFile, $ruleset);
    }

    private function getBuiltInRule($flag)
    {
        static $ruleMap = array(
            'cyclomatic_complexity' => 'CyclomaticComplexity',
            'npath_complexity' => 'NPathComplexity',
            'excessive_method_length' => 'ExcessiveMethodLength',
            'excessive_class_length' => 'ExcessiveClassLength',
            'excessive_parameter_list' => 'ExcessiveParameterList',
            'excessive_public_count' => 'ExcessivePublicCount',
            'too_many_fields' => 'TooManyFields',
            'too_many_methods' => 'TooManyMethods',
            'excessive_class_complexity' => 'ExcessiveClassComplexity',

            'exit_expression' => 'ExitExpression',
            'eval_expression' => 'EvalExpression',
            'goto_statement' => 'GotoStatement',
            'number_of_class_children' => 'NumberOfChildren',
            'depth_of_inheritance' => 'DepthOfInheritance',
            'coupling_between_objects' => 'CouplingBetweenObjects',

            'unused_private_field' => 'UnusedPrivateField',
            'unused_local_variable' => 'UnusedLocalVariable',
            'unused_private_method' => 'UnusedPrivateMethod',
            'unused_formal_parameter' => 'UnusedFormalParameter',

            'superglobals' => 'Superglobals',
            'camel_case_class_name' => 'CamelCaseClassName',
            'camel_case_property_name' => 'CamelCasePropertyName',
            'camel_case_method_name' => 'CamelCaseMethodName',
            'camel_case_parameter_name' => 'CamelCaseParameterName',
            'camel_case_variable_name' => 'CamelCaseVariableName',

            'short_variable' => 'ShortVariable',
            'long_variable' => 'LongVariable',
            'short_method' => 'ShortMethodName',
            'constructor_conflict' => 'ConstructorWithNameAsEnclosingClass',
            'constant_naming' => 'ConstantNamingConventions',
            'boolean_method_name' => 'BooleanGetMethodName',
        );

        if ( ! isset($ruleMap[$flag])) {
            throw new \LogicException(sprintf('Unknown rule "%s".', $flag));
        }

        return $ruleMap[$flag];
    }

    private function getBuiltInRulesetFile($key)
    {
        switch ($key) {
            case 'code_size_rules':
                return 'rulesets/codesize.xml';

            case 'design_rules':
                return 'rulesets/design.xml';

            case 'unused_code_rules':
                return 'rulesets/unusedcode.xml';

            case 'controversial_rules':
                return 'rulesets/controversial.xml';

            case 'naming_rules':
                return 'rulesets/naming.xml';

            default:
                throw new \LogicException(sprintf('There is no built in ruleset for "%s".', $key));
        }
    }
}
