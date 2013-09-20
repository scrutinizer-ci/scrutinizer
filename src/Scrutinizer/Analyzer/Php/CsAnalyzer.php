<?php

namespace Scrutinizer\Analyzer\Php;

use Scrutinizer\Analyzer\AbstractFileAnalyzer;
use Scrutinizer\Config\ConfigBuilder;
use Scrutinizer\Model\Comment;
use Scrutinizer\Model\File;
use Scrutinizer\Model\Project;
use Scrutinizer\Util\XmlUtils;
use Symfony\Component\Filesystem\Filesystem;
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
        $availableStandards = array('custom', 'PEAR', 'PHPCS', 'PSR1', 'PSR2', 'Squiz', 'Zend', 'WordPress');

        $builder
            ->globalConfig()
                ->scalarNode('command')
                    ->defaultValue('phpcs')
                ->end()
            ->end()
            ->perFileConfig()
                ->addDefaultsIfNotSet()
                ->beforeNormalization()->always(function($v) use ($availableStandards) {
                    if (isset($v['standard']) && ! in_array($v['standard'], $availableStandards, true)) {
                        $v['ruleset'] = $v['standard'];
                        $v['standard'] = 'custom';
                    }

                    return $v;
                })->end()
                ->children()
                    ->scalarNode('tab_width')
                        ->attribute('label', 'Tab Width')
                        ->attribute('help_inline', 'The number of spaces a tab represents.')
                        ->defaultValue(4)
                    ->end()
                    ->scalarNode('encoding')
                        ->attribute('label', 'File Encoding')
                        ->defaultValue('utf8')
                    ->end()
                    ->scalarNode('ruleset')
                        ->attribute('show_in_editor', false)
                    ->end()
                    ->enumNode('standard')
                        ->attribute('choices', array(
                            'custom' => 'Custom Standard (see sniffs below)',
                            'PEAR' => 'PEAR Standard',
                            'PHPCS' => 'PHP Code Sniffer Standard',
                            'PSR1'  => 'PSR1 Standard',
                            'PSR2'  => 'PSR2 Standard',
                            'Squiz' => 'Squiz Standard',
                            'Zend' => 'Zend Standard',
                            'WordPress' => 'WordPress Standard',
                        ))
                        ->values($availableStandards)
                        ->defaultValue('custom')
                    ->end()
                    ->arrayNode('sniffs')
                        ->addDefaultsIfNotSet()
                        ->attribute('depends_on', array('standard' => 'custom'))
                        ->children()
                            ->arrayNode('psr1')
                                ->addDefaultsIfNotSet()
                                ->children()
                                    ->arrayNode('classes')
                                        ->addDefaultsIfNotSet()
                                        ->attribute('type', 'choice')
                                        ->children()
                                            ->booleanNode('class_declaration_sniff')
                                                ->attribute('label', 'Each class must be in a file by itself and must be under a namespace (a top-level vendor name).')
                                                ->defaultValue(false)
                                            ->end()
                                        ->end()
                                    ->end()
                                    ->arrayNode('files')
                                        ->addDefaultsIfNotSet()
                                        ->attribute('type', 'choice')
                                        ->children()
                                            ->booleanNode('side_effects_sniff')
                                                ->attribute('label', 'A php file should either contain declarations with no side effects, or should just have logic (including side effects) with no declarations.')
                                                ->defaultValue(true)
                                            ->end()
                                        ->end()
                                    ->end()
                                ->end()
                            ->end()
                            ->arrayNode('generic')
                                ->addDefaultsIfNotSet()
                                ->children()
                                    ->arrayNode('code_analysis')
                                        ->addDefaultsIfNotSet()
                                        ->attribute('type', 'choice')
                                        ->children()
                                            ->booleanNode('unused_function_parameter_sniff')
                                                ->attribute('label', 'All parameters in a functions signature should be used within the function.')
                                                ->defaultValue(false)
                                            ->end()
                                            ->booleanNode('for_loop_with_test_function_call_sniff')
                                                ->attribute('label', 'For loops should not call functions inside the test for the loop when they can be computed beforehand.')
                                                ->defaultValue(true)
                                            ->end()
                                            ->booleanNode('unconditional_if_statement_sniff')
                                                ->attribute('label', 'If statements that are always evaluated should not be used.')
                                                ->defaultValue(false)
                                            ->end()
                                            ->booleanNode('empty_statement_sniff')
                                                ->attribute('label', 'Control Structures must have at least one statment inside of the body.')
                                                ->defaultValue(true)
                                            ->end()
                                            ->booleanNode('unnecessary_final_modifier_sniff')
                                                ->attribute('label', 'Methods should not be declared final inside of classes that are declared final.')
                                                ->defaultValue(true)
                                            ->end()
                                            ->booleanNode('for_loop_should_be_while_loop_sniff')
                                                ->attribute('label', 'For loops that have only a second expression (the condition) should be converted to while loops.')
                                                ->defaultValue(false)
                                            ->end()
                                            ->booleanNode('useless_overriding_method_sniff')
                                                ->attribute('label', 'Methods should not be defined that only call the parent method.')
                                                ->defaultValue(true)
                                            ->end()
                                            ->booleanNode('jumbled_incrementer_sniff')
                                                ->attribute('label', 'Incrementers in nested loops should use different variable names.')
                                                ->defaultValue(true)
                                            ->end()
                                        ->end()
                                    ->end()
                                    ->arrayNode('classes')
                                        ->addDefaultsIfNotSet()
                                        ->attribute('type', 'choice')
                                        ->children()
                                            ->booleanNode('duplicate_class_name_sniff')
                                                ->attribute('label', 'Class and Interface names should be unique in a project.  They should never be duplicated.')
                                                ->defaultValue(false)
                                            ->end()
                                        ->end()
                                    ->end()
                                    ->arrayNode('white_space')
                                        ->addDefaultsIfNotSet()
                                        ->attribute('type', 'choice')
                                        ->children()
                                            ->booleanNode('disallow_tab_indent_sniff')
                                                ->attribute('label', 'Spaces should be used for indentation instead of tabs.')
                                                ->defaultValue(false)
                                            ->end()
                                            ->booleanNode('scope_indent_sniff')
                                                ->attribute('label', 'Indentation for control structures, classes, and functions should be 4 spaces per level.')
                                                ->defaultValue(false)
                                            ->end()
                                            ->booleanNode('disallow_space_indent_sniff')
                                                ->attribute('label', 'Tabs should be used for indentation instead of spaces.')
                                                ->defaultValue(false)
                                            ->end()
                                        ->end()
                                    ->end()
                                    ->arrayNode('php')
                                        ->addDefaultsIfNotSet()
                                        ->attribute('type', 'choice')
                                        ->children()
                                            ->booleanNode('disallow_short_open_tag_sniff')
                                                ->attribute('label', 'Always use <?php ?> to delimit PHP code, not the <? ?> shorthand. This is the most portable way to include PHP code on differing operating systems and setups.')
                                                ->defaultValue(false)
                                            ->end()
                                            ->booleanNode('sapi_usage_sniff')
                                                ->attribute('label', 'The PHP_SAPI constant should be used instead of php_sapi_name().')
                                                ->defaultValue(false)
                                            ->end()
                                            ->booleanNode('no_silenced_errors_sniff')
                                                ->attribute('label', 'Suppressing Errors is not allowed.')
                                                ->defaultValue(false)
                                            ->end()
                                            ->booleanNode('deprecated_functions_sniff')
                                                ->attribute('label', 'Deprecated functions should not be used.')
                                                ->defaultValue(true)
                                            ->end()
                                            ->booleanNode('upper_case_constant_sniff')
                                                ->attribute('label', 'The <em>true</em>, <em>false</em> and <em>null</em> constants must always be uppercase.')
                                                ->defaultValue(false)
                                            ->end()
                                            ->booleanNode('closing_php_tag_sniff')
                                                ->attribute('label', 'All opening php tags should have a corresponding closing tag.')
                                                ->defaultValue(false)
                                            ->end()
                                            ->booleanNode('forbidden_functions_sniff')
                                                ->attribute('label', 'The forbidden functions sizeof and delete should not be used.')
                                                ->defaultValue(false)
                                            ->end()
                                            ->booleanNode('lower_case_constant_sniff')
                                                ->attribute('label', 'The <em>true</em>, <em>false</em> and <em>null</em> constants must always be lowercase.')
                                                ->defaultValue(false)
                                            ->end()
                                            ->booleanNode('character_before_php_opening_tag_sniff')
                                                ->attribute('label', 'The opening php tag should be the first item in the file.')
                                                ->defaultValue(true)
                                            ->end()
                                            ->booleanNode('lower_case_keyword_sniff')
                                                ->attribute('label', 'All PHP keywords should be lowercase.')
                                                ->defaultValue(false)
                                            ->end()
                                        ->end()
                                    ->end()
                                    ->arrayNode('formatting')
                                        ->addDefaultsIfNotSet()
                                        ->attribute('type', 'choice')
                                        ->children()
                                            ->booleanNode('multiple_statement_alignment_sniff')
                                                ->attribute('label', 'There should be one space on either side of an equals sign used to assign a value to a variable. In the case of a block of related assignments, more space may be inserted to promote readability.')
                                                ->defaultValue(false)
                                            ->end()
                                            ->booleanNode('no_space_after_cast_sniff')
                                                ->attribute('label', 'Spaces are not allowed after casting operators.')
                                                ->defaultValue(false)
                                            ->end()
                                            ->booleanNode('space_after_cast_sniff')
                                                ->attribute('label', 'Exactly one space is allowed after a cast.')
                                                ->defaultValue(false)
                                            ->end()
                                            ->booleanNode('disallow_multiple_statements_sniff')
                                                ->attribute('label', 'Multiple statements are not allowed on a single line.')
                                                ->defaultValue(false)
                                            ->end()
                                        ->end()
                                    ->end()
                                    ->arrayNode('functions')
                                        ->addDefaultsIfNotSet()
                                        ->attribute('type', 'choice')
                                        ->children()
                                            ->booleanNode('function_call_argument_spacing_sniff')
                                                ->attribute('label', 'Function arguments should have one space after a comma, and single spaces surrounding the equals sign for default values.')
                                                ->defaultValue(false)
                                            ->end()
                                            ->booleanNode('opening_function_brace_kernighan_ritchie_sniff')
                                                ->attribute('label', 'Function declarations follow the "Kernighan/Ritchie style". The function brace is on the same line as the function declaration. One space is required between the closing parenthesis and the brace.')
                                                ->defaultValue(false)
                                            ->end()
                                            ->booleanNode('opening_function_brace_bsd_allman_sniff')
                                                ->attribute('label', 'Function declarations follow the "BSD/Allman style". The function brace is on the line following the function declaration and is indented to the same column as the start of the function declaration.')
                                                ->defaultValue(false)
                                            ->end()
                                            ->booleanNode('call_time_pass_by_reference_sniff')
                                                ->attribute('label', 'Call-time pass-by-reference is not allowed. It should be declared in the function definition.')
                                                ->defaultValue(false)
                                            ->end()
                                        ->end()
                                    ->end()
                                    ->arrayNode('files')
                                        ->addDefaultsIfNotSet()
                                        ->attribute('type', 'choice')
                                        ->children()
                                            ->booleanNode('one_interface_per_file_sniff')
                                                ->attribute('label', 'There should only be one interface defined in a file.')
                                                ->defaultValue(false)
                                            ->end()
                                            ->booleanNode('end_file_newline_sniff')
                                                ->attribute('label', 'Files should end with a newline character.')
                                                ->defaultValue(false)
                                            ->end()
                                            ->booleanNode('line_length_sniff')
                                                ->attribute('label', 'It is recommended to keep lines at approximately 80 characters long for better code readability.')
                                                ->defaultValue(false)
                                            ->end()
                                            ->booleanNode('inline_html_sniff')
                                                ->attribute('label', 'Files that contain php code should only have php code and should not have any "inline html".')
                                                ->defaultValue(false)
                                            ->end()
                                            ->booleanNode('byte_order_mark_sniff')
                                                ->attribute('label', 'Byte Order Marks that may corrupt your application should not be used.  These include 0xefbbbf (UTF-8), 0xfeff (UTF-16 BE) and 0xfffe (UTF-16 LE).')
                                                ->defaultValue(false)
                                            ->end()
                                            ->booleanNode('end_file_no_newline_sniff')
                                                ->attribute('label', 'Files should not end with a newline character.')
                                                ->defaultValue(false)
                                            ->end()
                                            ->booleanNode('one_class_per_file_sniff')
                                                ->attribute('label', 'There should only be one class defined in a file.')
                                                ->defaultValue(false)
                                            ->end()
                                            ->booleanNode('line_endings_sniff')
                                                ->attribute('label', 'Unix-style endlines are preferred ("\\n" instead of "\\r\\n").')
                                                ->defaultValue(false)
                                            ->end()
                                        ->end()
                                    ->end()
                                    ->arrayNode('version_control')
                                        ->addDefaultsIfNotSet()
                                        ->attribute('type', 'choice')
                                        ->children()
                                            ->booleanNode('subversion_properties_sniff')
                                                ->attribute('label', 'All php files in a subversion repository should have the svn:keywords property set to \'Author Id Revision\' and the svn:eol-style property set to \'native\'.')
                                                ->defaultValue(false)
                                            ->end()
                                        ->end()
                                    ->end()
                                    ->arrayNode('commenting')
                                        ->addDefaultsIfNotSet()
                                        ->attribute('type', 'choice')
                                        ->children()
                                            ->booleanNode('fixme_sniff')
                                                ->attribute('label', 'FIXME Statements should be taken care of.')
                                                ->defaultValue(false)
                                            ->end()
                                            ->booleanNode('todo_sniff')
                                                ->attribute('label', 'TODO Statements should be taken care of.')
                                                ->defaultValue(false)
                                            ->end()
                                        ->end()
                                    ->end()
                                    ->arrayNode('control_structures')
                                        ->addDefaultsIfNotSet()
                                        ->attribute('type', 'choice')
                                        ->children()
                                            ->booleanNode('inline_control_structure_sniff')
                                                ->attribute('label', 'Control Structures should use braces.')
                                                ->defaultValue(false)
                                            ->end()
                                        ->end()
                                    ->end()
                                    ->arrayNode('strings')
                                        ->addDefaultsIfNotSet()
                                        ->attribute('type', 'choice')
                                        ->children()
                                            ->booleanNode('unnecessary_string_concat_sniff')
                                                ->attribute('label', 'Strings should not be concatenated together.')
                                                ->defaultValue(false)
                                            ->end()
                                        ->end()
                                    ->end()
                                    ->arrayNode('naming_conventions')
                                        ->addDefaultsIfNotSet()
                                        ->attribute('type', 'choice')
                                        ->children()
                                            ->booleanNode('camel_caps_function_name_sniff')
                                                ->attribute('label', 'Functions should use camelCaps format for their names. Only PHP\'s magic methods should use a double underscore prefix.')
                                                ->defaultValue(false)
                                            ->end()
                                            ->booleanNode('constructor_name_sniff')
                                                ->attribute('label', 'Constructors should be named __construct, not after the class.')
                                                ->defaultValue(false)
                                            ->end()
                                            ->booleanNode('upper_case_constant_name_sniff')
                                                ->attribute('label', 'Constants should always be all-uppercase, with underscores to separate words.')
                                                ->defaultValue(false)
                                            ->end()
                                        ->end()
                                    ->end()
                                    ->arrayNode('metrics')
                                        ->addDefaultsIfNotSet()
                                        ->attribute('type', 'choice')
                                        ->children()
                                            ->booleanNode('cyclomatic_complexity_sniff')
                                                ->attribute('label', 'Functions should not have a cyclomatic complexity greater than 20, and should try to stay below a complexity of 10.')
                                                ->defaultValue(false)
                                            ->end()
                                            ->booleanNode('nesting_level_sniff')
                                                ->attribute('label', 'Functions should not have a nesting level greater than 10, and should try to stay below 5.')
                                                ->defaultValue(false)
                                            ->end()
                                        ->end()
                                    ->end()
                                ->end()
                            ->end()
                            ->arrayNode('zend')
                                ->addDefaultsIfNotSet()
                                ->children()
                                    ->arrayNode('debug')
                                        ->addDefaultsIfNotSet()
                                        ->attribute('type', 'choice')
                                        ->children()
                                            ->booleanNode('code_analyzer_sniff')
                                                ->attribute('label', 'PHP Code should pass the zend code analyzer.')
                                                ->defaultValue(false)
                                            ->end()
                                        ->end()
                                    ->end()
                                    ->arrayNode('files')
                                        ->addDefaultsIfNotSet()
                                        ->attribute('type', 'choice')
                                        ->children()
                                            ->booleanNode('closing_tag_sniff')
                                                ->attribute('label', 'Files should not have closing php tags.')
                                                ->defaultValue(false)
                                            ->end()
                                        ->end()
                                    ->end()
                                    ->arrayNode('naming_conventions')
                                        ->addDefaultsIfNotSet()
                                        ->attribute('type', 'choice')
                                        ->children()
                                            ->booleanNode('valid_variable_name_sniff')
                                                ->attribute('label', 'Variable names should be camelCased with the first letter lowercase.  Private and protected member variables should begin with an underscore')
                                                ->defaultValue(false)
                                            ->end()
                                        ->end()
                                    ->end()
                                ->end()
                            ->end()
                            ->arrayNode('squiz')
                                ->addDefaultsIfNotSet()
                                ->children()
                                    ->arrayNode('scope')
                                        ->addDefaultsIfNotSet()
                                        ->attribute('type', 'choice')
                                        ->children()
                                            ->booleanNode('static_this_usage_sniff')
                                                ->attribute('label', 'Static methods should not use $this.')
                                                ->defaultValue(true)
                                            ->end()
                                            ->booleanNode('method_scope_sniff')
                                                ->attribute('label', 'Verifies that methods have scope modifiers')
                                                ->defaultValue(true)
                                            ->end()
                                            ->booleanNode('member_var_scope_sniff')
                                                ->attribute('label', 'Verifies that properties have scope modifiers.')
                                                ->defaultValue(true)
                                            ->end()
                                        ->end()
                                    ->end()
                                    ->arrayNode('code_analysis')
                                        ->addDefaultsIfNotSet()
                                        ->attribute('type', 'choice')
                                        ->children()
                                            ->booleanNode('empty_statement_sniff')
                                                ->attribute('label', 'This sniff class detects empty statement.')
                                                ->defaultValue(false)
                                            ->end()
                                        ->end()
                                    ->end()
                                    ->arrayNode('classes')
                                        ->addDefaultsIfNotSet()
                                        ->attribute('type', 'choice')
                                        ->children()
                                            ->booleanNode('lowercase_class_keywords_sniff')
                                                ->attribute('label', 'The php keywords class, interface, trait, extends, implements, abstract, final, var, and const should be lowercase.')
                                                ->defaultValue(false)
                                            ->end()
                                            ->booleanNode('valid_class_name_sniff')
                                                ->attribute('label', 'Verifies that class names are valid.')
                                                ->defaultValue(false)
                                            ->end()
                                            ->booleanNode('class_file_name_sniff')
                                                ->attribute('label', 'Tests that the file name and the name of the class contained within the file match.')
                                                ->defaultValue(false)
                                            ->end()
                                            ->booleanNode('self_member_reference_sniff')
                                                ->attribute('label', 'The self keyword should be used instead of the current class name, should be lowercase, and should not have spaces before or after it.')
                                                ->defaultValue(true)
                                            ->end()
                                            ->booleanNode('class_declaration_sniff')
                                                ->attribute('label', 'Checks the declaration of the class and its inheritance is correct.')
                                                ->defaultValue(false)
                                            ->end()
                                        ->end()
                                    ->end()
                                    ->arrayNode('arrays')
                                        ->addDefaultsIfNotSet()
                                        ->attribute('type', 'choice')
                                        ->children()
                                            ->booleanNode('array_bracket_spacing_sniff')
                                                ->attribute('label', 'When referencing arrays you should not put whitespace around the opening bracket or before the closing bracket.')
                                                ->defaultValue(false)
                                            ->end()
                                            ->booleanNode('array_declaration_sniff')
                                                ->attribute('label', 'This standard covers all array declarations, regardless of the number and type of values contained within the array.')
                                                ->defaultValue(false)
                                            ->end()
                                        ->end()
                                    ->end()
                                    ->arrayNode('objects')
                                        ->addDefaultsIfNotSet()
                                        ->attribute('type', 'choice')
                                        ->children()
                                            ->booleanNode('object_instantiation_sniff')
                                                ->attribute('label', 'Ensures objects are assigned to a variable when instantiated.')
                                                ->defaultValue(false)
                                            ->end()
                                        ->end()
                                    ->end()
                                    ->arrayNode('white_space')
                                        ->addDefaultsIfNotSet()
                                        ->attribute('type', 'choice')
                                        ->children()
                                            ->booleanNode('logical_operator_spacing_sniff')
                                                ->attribute('label', 'Verifies that logical operators have valid spacing surrounding them.')
                                                ->defaultValue(false)
                                            ->end()
                                            ->booleanNode('language_construct_spacing_sniff')
                                                ->attribute('label', 'The php constructs echo, print, return, include, include_once, require, require_once, and new should have one space after them.')
                                                ->defaultValue(false)
                                            ->end()
                                            ->booleanNode('operator_spacing_sniff')
                                                ->attribute('label', 'Verifies that operators have valid spacing surrounding them.')
                                                ->defaultValue(false)
                                            ->end()
                                            ->booleanNode('control_structure_spacing_sniff')
                                                ->attribute('label', 'Checks that control structures have the correct spacing around brackets.')
                                                ->defaultValue(false)
                                            ->end()
                                            ->booleanNode('function_opening_brace_space_sniff')
                                                ->attribute('label', 'Checks that there is no empty line after the opening brace of a function.')
                                                ->defaultValue(false)
                                            ->end()
                                            ->booleanNode('function_spacing_sniff')
                                                ->attribute('label', 'Checks whitespace between methods in a class or interface.')
                                                ->defaultValue(false)
                                            ->end()
                                            ->booleanNode('superfluous_whitespace_sniff')
                                                ->attribute('label', 'Checks that no whitespace proceeds the first content of the file, exists after the last content of the file, resides after content on any line, or are two empty lines in functions.')
                                                ->defaultValue(false)
                                            ->end()
                                            ->booleanNode('member_var_spacing_sniff')
                                                ->attribute('label', 'Verifies that class properties are spaced correctly.')
                                                ->defaultValue(false)
                                            ->end()
                                            ->booleanNode('scope_closing_brace_sniff')
                                                ->attribute('label', 'Checks that the closing braces of scopes are aligned correctly')
                                                ->defaultValue(false)
                                            ->end()
                                            ->booleanNode('scope_keyword_spacing_sniff')
                                                ->attribute('label', 'The php keywords static, public, private, and protected should have one space after them.')
                                                ->defaultValue(false)
                                            ->end()
                                            ->booleanNode('function_closing_brace_space_sniff')
                                                ->attribute('label', 'Checks that there is one empty line before the closing brace of a function.')
                                                ->defaultValue(false)
                                            ->end()
                                            ->booleanNode('semicolon_spacing_sniff')
                                                ->attribute('label', 'Semicolons should not have spaces before them.')
                                                ->defaultValue(false)
                                            ->end()
                                            ->booleanNode('cast_spacing_sniff')
                                                ->attribute('label', 'Casts should not have whitespace inside the parentheses.')
                                                ->defaultValue(false)
                                            ->end()
                                            ->booleanNode('object_operator_spacing_sniff')
                                                ->attribute('label', 'The object operator (->) should not have any space around it.')
                                                ->defaultValue(false)
                                            ->end()
                                        ->end()
                                    ->end()
                                    ->arrayNode('php')
                                        ->addDefaultsIfNotSet()
                                        ->attribute('type', 'choice')
                                        ->children()
                                            ->booleanNode('disallow_comparison_assignment_sniff')
                                                ->attribute('label', 'Ensures that the value of a comparison is not assigned to a variable.')
                                                ->defaultValue(false)
                                            ->end()
                                            ->booleanNode('disallow_size_functions_in_loops_sniff')
                                                ->attribute('label', 'Bans the use of size-based functions in loop conditions, i.e. for ($i=0;$i<count($a);$i++).')
                                                ->defaultValue(false)
                                            ->end()
                                            ->booleanNode('heredoc_sniff')
                                                ->attribute('label', 'Heredocs are prohibited.')
                                                ->defaultValue(false)
                                            ->end()
                                            ->booleanNode('disallow_ob_end_flush_sniff')
                                                ->attribute('label', 'Use of ob_end_flush() is not allowed; use ob_get_contents() and ob_end_clean() instead')
                                                ->defaultValue(false)
                                            ->end()
                                            ->booleanNode('inner_functions_sniff')
                                                ->attribute('label', 'The use of inner functions is forbidden')
                                                ->defaultValue(false)
                                            ->end()
                                            ->booleanNode('forbidden_functions_sniff')
                                                ->attribute('label', 'Discourages the use of alias functions that are kept in PHP for compatibility with older versions. Can be used to forbid the use of any function.')
                                                ->defaultValue(false)
                                            ->end()
                                            ->booleanNode('eval_sniff')
                                                ->attribute('label', 'The use of eval() is discouraged.')
                                                ->defaultValue(false)
                                            ->end()
                                            ->booleanNode('lowercase_p_h_p_functions_sniff')
                                                ->attribute('label', 'Ensures all calls to inbuilt PHP functions are lowercase.')
                                                ->defaultValue(false)
                                            ->end()
                                            ->booleanNode('discouraged_functions_sniff')
                                                ->attribute('label', 'Discourages the use of debug functions.')
                                                ->defaultValue(false)
                                            ->end()
                                            ->booleanNode('embedded_php_sniff')
                                                ->attribute('label', 'Checks the indentation of embedded PHP code segments.')
                                                ->defaultValue(false)
                                            ->end()
                                            ->booleanNode('commented_out_code_sniff')
                                                ->attribute('label', 'Warn about commented out code.')
                                                ->defaultValue(false)
                                            ->end()
                                            ->booleanNode('disallow_inline_if_sniff')
                                                ->attribute('label', 'Inline IF statements are not allowed')
                                                ->defaultValue(false)
                                            ->end()
                                            ->booleanNode('disallow_multiple_assignments_sniff')
                                                ->attribute('label', 'Ensures that there is only one value assignment on a line, and that it is the first thing on the line.')
                                                ->defaultValue(false)
                                            ->end()
                                            ->booleanNode('global_keyword_sniff')
                                                ->attribute('label', 'Stops the usage of the "global" keyword.')
                                                ->defaultValue(false)
                                            ->end()
                                            ->booleanNode('non_executable_code_sniff')
                                                ->attribute('label', 'Warns about code that can never been executed. This happens when a function returns before the code, or a break ends execution of a statement etc.')
                                                ->defaultValue(true)
                                            ->end()
                                        ->end()
                                    ->end()
                                    ->arrayNode('formatting')
                                        ->addDefaultsIfNotSet()
                                        ->attribute('type', 'choice')
                                        ->children()
                                            ->booleanNode('operator_bracket_sniff')
                                                ->attribute('label', 'Tests that all arithmetic operations are bracketed.')
                                                ->defaultValue(false)
                                            ->end()
                                        ->end()
                                    ->end()
                                    ->arrayNode('functions')
                                        ->addDefaultsIfNotSet()
                                        ->attribute('type', 'choice')
                                        ->children()
                                            ->booleanNode('lowercase_function_keywords_sniff')
                                                ->attribute('label', 'The php keywords function, public, private, protected, and static should be lowercase.')
                                                ->defaultValue(false)
                                            ->end()
                                            ->booleanNode('global_function_sniff')
                                                ->attribute('label', 'Tests for functions outside of classes and suggests to use a static method instead.')
                                                ->defaultValue(false)
                                            ->end()
                                            ->booleanNode('function_duplicate_argument_sniff')
                                                ->attribute('label', 'All PHP built-in functions should be lowercased when called.')
                                                ->defaultValue(false)
                                            ->end()
                                            ->booleanNode('multi_line_function_declaration_sniff')
                                                ->attribute('label', 'Ensure single and multi-line function declarations are defined correctly.')
                                                ->defaultValue(false)
                                            ->end()
                                            ->booleanNode('function_declaration_argument_spacing_sniff')
                                                ->attribute('label', 'Checks that arguments in function declarations are spaced correctly.')
                                                ->defaultValue(false)
                                            ->end()
                                            ->booleanNode('function_declaration_sniff')
                                                ->attribute('label', 'Checks the function declaration is correct.')
                                                ->defaultValue(false)
                                            ->end()
                                        ->end()
                                    ->end()
                                    ->arrayNode('files')
                                        ->addDefaultsIfNotSet()
                                        ->attribute('type', 'choice')
                                        ->children()
                                            ->booleanNode('file_extension_sniff')
                                                ->attribute('label', 'Checks that there is at least a class or interface in a file ending with .php and suggest to use .inc otherwise.')
                                                ->defaultValue(false)
                                            ->end()
                                        ->end()
                                    ->end()
                                    ->arrayNode('commenting')
                                        ->addDefaultsIfNotSet()
                                        ->attribute('type', 'choice')
                                        ->children()
                                            ->booleanNode('inline_comment_sniff')
                                                ->attribute('label', 'Checks that there is adequate spacing between comments.')
                                                ->defaultValue(false)
                                            ->end()
                                            ->booleanNode('post_statement_comment_sniff')
                                                ->attribute('label', 'Checks to ensure that there are no comments after statements.')
                                                ->defaultValue(false)
                                            ->end()
                                            ->booleanNode('class_comment_sniff')
                                                ->attribute('label', 'Verifies for a Class Doc-Comment: a) class comment exists, b) there is exactly one blank line before the comment, c) short description ends with ".", d) there is a blank line after the description, e) long description ends with ".", f) there is a blank line between long description and tags, g) since tag has the format (x.x.x)')
                                                ->defaultValue(false)
                                            ->end()
                                            ->booleanNode('doc_comment_alignment_sniff')
                                                ->attribute('label', 'The asterisks in a doc comment should align, and there should be one space between the asterisk and tags.')
                                                ->defaultValue(false)
                                            ->end()
                                            ->booleanNode('block_comment_sniff')
                                                ->attribute('label', 'Verifies that block comments are used appropriately.')
                                                ->defaultValue(false)
                                            ->end()
                                            ->booleanNode('function_comment_sniff')
                                                ->attribute('label', 'Verifies for a Function Doc-Comment: a) exists, b) blank line after short description, c) blank line after long description and tags, d) parameter names match those in the method, e) parameter names are in correct order, f) parameter comments are complete, g) arrays and classes are type-hinted, h) type-hint and @param match, i) @return exists, j) @throw tags have comment')
                                                ->defaultValue(false)
                                            ->end()
                                            ->booleanNode('function_comment_throw_tag_sniff')
                                                ->attribute('label', 'If a function throws any exceptions, they should be documented in a @throws tag.')
                                                ->defaultValue(false)
                                            ->end()
                                            ->booleanNode('variable_comment_sniff')
                                                ->attribute('label', 'Parses and verifies a properties\' doc comment.')
                                                ->defaultValue(false)
                                            ->end()
                                            ->booleanNode('empty_catch_comment_sniff')
                                                ->attribute('label', 'Checks for empty Catch clause. Catch clause must at least have comment')
                                                ->defaultValue(false)
                                            ->end()
                                            ->booleanNode('file_comment_sniff')
                                                ->attribute('label', 'Parses and verifies the file doc comment.')
                                                ->defaultValue(false)
                                            ->end()
                                            ->booleanNode('long_condition_closing_comment_sniff')
                                                ->attribute('label', 'Checks that there is a //end ... comment at the end of long conditions.')
                                                ->defaultValue(false)
                                            ->end()
                                            ->booleanNode('closing_declaration_comment_sniff')
                                                ->attribute('label', 'Checks that there is a //end ... comments at the end of classes, interfaces and functions.')
                                                ->defaultValue(false)
                                            ->end()
                                        ->end()
                                    ->end()
                                    ->arrayNode('control_structures')
                                        ->addDefaultsIfNotSet()
                                        ->attribute('type', 'choice')
                                        ->children()
                                            ->booleanNode('control_signature_sniff')
                                                ->attribute('label', 'Verifies that control statements conform to their coding standards.')
                                                ->defaultValue(false)
                                            ->end()
                                            ->booleanNode('lowercase_declaration_sniff')
                                                ->attribute('label', 'The php keywords if, else, elseif, foreach, for, do, switch, while, try, and catch should be lowercase.')
                                                ->defaultValue(false)
                                            ->end()
                                            ->booleanNode('inline_if_declaration_sniff')
                                                ->attribute('label', 'Tests the spacing of shorthand IF statements.')
                                                ->defaultValue(false)
                                            ->end()
                                            ->booleanNode('for_each_loop_declaration_sniff')
                                                ->attribute('label', 'There should be a space between each element of a foreach loop and the as keyword should be lowercase.')
                                                ->defaultValue(false)
                                            ->end()
                                            ->booleanNode('for_loop_declaration_sniff')
                                                ->attribute('label', 'In a for loop declaration, there should be no space inside the brackets and there should be 0 spaces before and 1 space after semicolons.')
                                                ->defaultValue(false)
                                            ->end()
                                            ->booleanNode('switch_declaration_sniff')
                                                ->attribute('label', 'Ensures all the breaks and cases are aligned correctly according to their parent switch\'s alignment and enforces other switch formatting.')
                                                ->defaultValue(false)
                                            ->end()
                                            ->booleanNode('else_if_declaration_sniff')
                                                ->attribute('label', 'Verifies that there are not elseif statements. The else and the if should be separated by a space.')
                                                ->defaultValue(false)
                                            ->end()
                                        ->end()
                                    ->end()
                                    ->arrayNode('strings')
                                        ->addDefaultsIfNotSet()
                                        ->attribute('type', 'choice')
                                        ->children()
                                            ->booleanNode('echoed_strings_sniff')
                                                ->attribute('label', 'Simple strings should not be enclosed in parentheses when being echoed.')
                                                ->defaultValue(false)
                                            ->end()
                                            ->booleanNode('concatenation_spacing_sniff')
                                                ->attribute('label', 'Makes sure there are no spaces between the concatenation operator (.) and the strings being concatenated.')
                                                ->defaultValue(false)
                                            ->end()
                                            ->booleanNode('double_quote_usage_sniff')
                                                ->attribute('label', 'Makes sure that any use of Double Quotes ("") are warranted, suggests to use (\'\') instead.')
                                                ->defaultValue(false)
                                            ->end()
                                        ->end()
                                    ->end()
                                    ->arrayNode('naming_conventions')
                                        ->addDefaultsIfNotSet()
                                        ->attribute('type', 'choice')
                                        ->children()
                                            ->booleanNode('valid_function_name_sniff')
                                                ->attribute('label', 'Ensures method names are correct depending on whether they are public or private, and that functions are named correctly.')
                                                ->defaultValue(false)
                                            ->end()
                                            ->booleanNode('valid_variable_name_sniff')
                                                ->attribute('label', 'Checks the naming of variables and member variables.')
                                                ->defaultValue(false)
                                            ->end()
                                            ->booleanNode('constant_case_sniff')
                                                ->attribute('label', 'Ensures TRUE, FALSE and NULL are uppercase.')
                                                ->defaultValue(false)
                                            ->end()
                                        ->end()
                                    ->end()
                                    ->arrayNode('operators')
                                        ->addDefaultsIfNotSet()
                                        ->attribute('type', 'choice')
                                        ->children()
                                            ->booleanNode('increment_decrement_usage_sniff')
                                                ->attribute('label', 'Tests that the ++ operators are used when possible and not used when it makes the code confusing.')
                                                ->defaultValue(false)
                                            ->end()
                                            ->booleanNode('valid_logical_operators_sniff')
                                                ->attribute('label', 'Checks to ensure that the logical operators \'and\' and \'or\' are not used. Use the && and || operators instead.')
                                                ->defaultValue(false)
                                            ->end()
                                            ->booleanNode('comparison_operator_usage_sniff')
                                                ->attribute('label', 'Enforces the use of IDENTICAL (===) type operators rather than EQUAL (==) operators where applicable.')
                                                ->defaultValue(false)
                                            ->end()
                                        ->end()
                                    ->end()
                                ->end()
                            ->end()
                            ->arrayNode('my_source')
                                ->addDefaultsIfNotSet()
                                ->children()
                                    ->arrayNode('php')
                                        ->addDefaultsIfNotSet()
                                        ->attribute('type', 'choice')
                                        ->children()
                                            ->booleanNode('return_function_value_sniff')
                                                ->attribute('label', 'The results of a function should be assigned to a variable before being returned.')
                                                ->defaultValue(false)
                                            ->end()
                                            ->booleanNode('eval_object_factory_sniff')
                                                ->attribute('label', 'Ensures that eval() is not used to create objects.')
                                                ->defaultValue(false)
                                            ->end()
                                        ->end()
                                    ->end()
                                    ->arrayNode('debug')
                                        ->addDefaultsIfNotSet()
                                        ->attribute('type', 'choice')
                                        ->children()
                                            ->booleanNode('debug_code_sniff')
                                                ->attribute('label', 'Warns about the use of debug code.')
                                                ->defaultValue(false)
                                            ->end()
                                        ->end()
                                    ->end()
                                    ->arrayNode('commenting')
                                        ->addDefaultsIfNotSet()
                                        ->attribute('type', 'choice')
                                        ->children()
                                            ->booleanNode('function_comment_sniff')
                                                ->attribute('label', 'Parses function comments. Same as SQUIZ standard, but adds support for @api tags.')
                                                ->defaultValue(false)
                                            ->end()
                                        ->end()
                                    ->end()
                                ->end()
                            ->end()
                            ->arrayNode('psr2')
                                ->addDefaultsIfNotSet()
                                ->children()
                                    ->arrayNode('classes')
                                        ->addDefaultsIfNotSet()
                                        ->attribute('type', 'choice')
                                        ->children()
                                            ->booleanNode('property_declaration_sniff')
                                                ->attribute('label', 'Property names should not be prefixed with an underscore to indicate visibility.  Visibility should be used to declare properties rather than the var keyword.  Only one property should be declared within a statement.')
                                                ->defaultValue(false)
                                            ->end()
                                            ->booleanNode('class_declaration_sniff')
                                                ->attribute('label', 'There should be exactly 1 space between the abstract or final keyword and the class keyword and between the class keyword and the class name.  The extends and implements keywords, if present, must be on the same line as the class name.  When interfaces implemented are spread over multiple lines, there should be exactly 1 interface metioned per line indented by 1 level.  The closing brace of the class must go on the first line after the body of the class and must be on a line by itself.')
                                                ->defaultValue(false)
                                            ->end()
                                        ->end()
                                    ->end()
                                    ->arrayNode('methods')
                                        ->addDefaultsIfNotSet()
                                        ->attribute('type', 'choice')
                                        ->children()
                                            ->booleanNode('method_declaration_sniff')
                                                ->attribute('label', 'Method names should not be prefixed with an underscore to indicate visibility.  The static keyword, when present, should come after the visibility declaration, and the final and abstract keywords should come before.')
                                                ->defaultValue(false)
                                            ->end()
                                        ->end()
                                    ->end()
                                    ->arrayNode('namespaces')
                                        ->addDefaultsIfNotSet()
                                        ->attribute('type', 'choice')
                                        ->children()
                                            ->booleanNode('namespace_declaration_sniff')
                                                ->attribute('label', 'There must be one blank line after the namespace declaration.')
                                                ->defaultValue(false)
                                            ->end()
                                            ->booleanNode('use_declaration_sniff')
                                                ->attribute('label', 'Each use declaration must contain only one namespace and must come after the first namespace declaration.  There should be one blank line after the final use statement.')
                                                ->defaultValue(false)
                                            ->end()
                                        ->end()
                                    ->end()
                                    ->arrayNode('files')
                                        ->addDefaultsIfNotSet()
                                        ->attribute('type', 'choice')
                                        ->children()
                                            ->booleanNode('end_file_newline_sniff')
                                                ->attribute('label', 'PHP Files should end with exactly one newline.')
                                                ->defaultValue(false)
                                            ->end()
                                        ->end()
                                    ->end()
                                    ->arrayNode('control_structures')
                                        ->addDefaultsIfNotSet()
                                        ->attribute('type', 'choice')
                                        ->children()
                                            ->booleanNode('control_structure_spacing_sniff')
                                                ->attribute('label', 'Control Structures should have 0 spaces after opening parentheses and 0 spaces before closing parentheses.')
                                                ->defaultValue(false)
                                            ->end()
                                            ->booleanNode('switch_declaration_sniff')
                                                ->attribute('label', 'Case statments should be indented 4 spaces from the switch keyword.  It should also be followed by a space.  Colons in switch declarations should not be preceded by whitespace.  Break statements should be indented 4 more spaces from the case statement.  There must be a comment when falling through from one case into the next.')
                                                ->defaultValue(false)
                                            ->end()
                                            ->booleanNode('else_if_declaration_sniff')
                                                ->attribute('label', 'PHP\'s elseif keyword should be used instead of else if.')
                                                ->defaultValue(false)
                                            ->end()
                                        ->end()
                                    ->end()
                                ->end()
                            ->end()
                            ->arrayNode('pear')
                                ->addDefaultsIfNotSet()
                                ->children()
                                    ->arrayNode('classes')
                                        ->addDefaultsIfNotSet()
                                        ->attribute('type', 'choice')
                                        ->children()
                                            ->booleanNode('class_declaration_sniff')
                                                ->attribute('label', 'The opening brace of a class must be on the line after the definition by itself.')
                                                ->defaultValue(false)
                                            ->end()
                                        ->end()
                                    ->end()
                                    ->arrayNode('white_space')
                                        ->addDefaultsIfNotSet()
                                        ->attribute('type', 'choice')
                                        ->children()
                                            ->booleanNode('object_operator_indent_sniff')
                                                ->attribute('label', 'Chained object operators when spread out over multiple lines should be the first thing on the line and be indented by 1 level.')
                                                ->defaultValue(false)
                                            ->end()
                                            ->booleanNode('scope_indent_sniff')
                                                ->attribute('label', 'Any scope openers except for switch statements should be indented 1 level.  This includes classes, functions, and control structures.')
                                                ->defaultValue(false)
                                            ->end()
                                            ->booleanNode('scope_closing_brace_sniff')
                                                ->attribute('label', 'Closing braces should be indented at the same level as the beginning of the scope.')
                                                ->defaultValue(false)
                                            ->end()
                                        ->end()
                                    ->end()
                                    ->arrayNode('formatting')
                                        ->addDefaultsIfNotSet()
                                        ->attribute('type', 'choice')
                                        ->children()
                                            ->booleanNode('multi_line_assignment_sniff')
                                                ->attribute('label', 'Multi-line assignment should have the equals sign be the first item on the second line indented correctly.')
                                                ->defaultValue(false)
                                            ->end()
                                        ->end()
                                    ->end()
                                    ->arrayNode('functions')
                                        ->addDefaultsIfNotSet()
                                        ->attribute('type', 'choice')
                                        ->children()
                                            ->booleanNode('function_call_signature_sniff')
                                                ->attribute('label', 'Functions should be called with no spaces between the function name, the opening parenthesis, and the first parameter; and no space between the last parameter, the closing parenthesis, and the semicolon.')
                                                ->defaultValue(false)
                                            ->end()
                                            ->booleanNode('function_declaration_sniff')
                                                ->attribute('label', 'There should be exactly 1 space after the function keyword and 1 space on each side of the use keyword.  Closures should use the Kernighan/Ritchie Brace style and other single-line functions should use the BSD/Allman style.  Multi-line function declarations should have the parameter lists indented one level with the closing parenthesis on a newline followed by a single space and the opening brace of the function.')
                                                ->defaultValue(false)
                                            ->end()
                                            ->booleanNode('valid_default_value_sniff')
                                                ->attribute('label', 'Arguments with default values go at the end of the argument list.')
                                                ->defaultValue(false)
                                            ->end()
                                        ->end()
                                    ->end()
                                    ->arrayNode('files')
                                        ->addDefaultsIfNotSet()
                                        ->attribute('type', 'choice')
                                        ->children()
                                            ->booleanNode('including_file_sniff')
                                                ->attribute('label', 'Anywhere you are unconditionally including a class file, use <em>require_once</em>. Anywhere you are conditionally including a class file (for example, factory methods), use <em>include_once</em>. Either of these will ensure that class files are included only once. They share the same file list, so you don\'t need to worry about mixing them - a file included with <em>require_once</em> will not be included again by <em>include_once</em>.')
                                                ->defaultValue(false)
                                            ->end()
                                        ->end()
                                    ->end()
                                    ->arrayNode('commenting')
                                        ->addDefaultsIfNotSet()
                                        ->attribute('type', 'choice')
                                        ->children()
                                            ->booleanNode('inline_comment_sniff')
                                                ->attribute('label', 'Perl-style # comments are not allowed.')
                                                ->defaultValue(false)
                                            ->end()
                                            ->booleanNode('class_comment_sniff')
                                                ->attribute('label', 'Classes and interfaces must have a non-empty doc comment.  The short description must be on the second line of the comment.  Each description must have one blank comment line before and after.  There must be one blank line before the tags in the comments.  A @version tag must be in Release: package_version format.')
                                                ->defaultValue(false)
                                            ->end()
                                            ->booleanNode('function_comment_sniff')
                                                ->attribute('label', 'Functions must have a non-empty doc comment.  The short description must be on the second line of the comment.  Each description must have one blank comment line before and after.  There must be one blank line before the tags in the comments.  There must be a tag for each of the parameters in the right order with the right variable names with a comment.  There must be a return tag.  Any throw tag must have an exception class.')
                                                ->defaultValue(false)
                                            ->end()
                                            ->booleanNode('file_comment_sniff')
                                                ->attribute('label', 'Files must have a non-empty doc comment.  The short description must be on the second line of the comment.  Each description must have one blank comment line before and after.  There must be one blank line before the tags in the comments.  There must be a category, package, author, license, and link tag.  There may only be one category, package, subpackage, license, version, since and deprecated tag.  The tags must be in the order category, package, subpackage, author, copyright, license, version, link, see, since, and deprecated.  The php version must be specified.')
                                                ->defaultValue(false)
                                            ->end()
                                        ->end()
                                    ->end()
                                    ->arrayNode('control_structures')
                                        ->addDefaultsIfNotSet()
                                        ->attribute('type', 'choice')
                                        ->children()
                                            ->booleanNode('control_signature_sniff')
                                                ->attribute('label', 'Control structures should use one space around the parentheses in conditions.  The opening brace should be preceded by one space and should be at the end of the line.')
                                                ->defaultValue(false)
                                            ->end()
                                            ->booleanNode('multi_line_condition_sniff')
                                                ->attribute('label', 'Multi-line if conditions should be indented one level and each line should begin with a boolean operator.  The end parenthesis should be on a new line.')
                                                ->defaultValue(false)
                                            ->end()
                                        ->end()
                                    ->end()
                                    ->arrayNode('naming_conventions')
                                        ->addDefaultsIfNotSet()
                                        ->attribute('type', 'choice')
                                        ->children()
                                            ->booleanNode('valid_function_name_sniff')
                                                ->attribute('label', 'Functions and methods should be named using the "studly caps" style (also referred to as "bumpy case" or "camel caps"). Functions should in addition have the package name as a prefix, to avoid name collisions between packages. The initial letter of the name (after the prefix) is lowercase, and each letter that starts a new "word" is capitalized.')
                                                ->defaultValue(false)
                                            ->end()
                                            ->booleanNode('valid_variable_name_sniff')
                                                ->attribute('label', 'Private member variable names should be prefixed with an underscore and public/protected variable names should not.')
                                                ->defaultValue(false)
                                            ->end()
                                            ->booleanNode('valid_class_name_sniff')
                                                ->attribute('label', 'Classes should be given descriptive names. Avoid using abbreviations where possible. Class names should always begin with an uppercase letter. The PEAR class hierarchy is also reflected in the class name, each level of the hierarchy separated with a single underscore.')
                                                ->defaultValue(false)
                                            ->end()
                                        ->end()
                                    ->end()
                                ->end()
                            ->end()
                            ->arrayNode('wordpress')
                                ->addDefaultsIfNotSet()
                                ->children()
                                    ->arrayNode('arrays')
                                        ->addDefaultsIfNotSet()
                                        ->attribute('type', 'choice')
                                        ->children()
                                            ->booleanNode('array_declaration_sniff')
                                                ->attribute('label', 'Enforces WordPress array format.')
                                                ->defaultValue(false)
                                            ->end()
                                        ->end()
                                    ->end()
                                    ->arrayNode('classes')
                                        ->addDefaultsIfNotSet()
                                        ->attribute('type', 'choice')
                                        ->children()
                                            ->booleanNode('valid_class_name_sniff')
                                                ->attribute('label', 'Ensures classes are in camel caps, and the first letter is capitalised.')
                                                ->defaultValue(false)
                                            ->end()
                                        ->end()
                                    ->end()
                                    ->arrayNode('files')
                                        ->addDefaultsIfNotSet()
                                        ->attribute('type', 'choice')
                                        ->children()
                                            ->booleanNode('file_name_sniff')
                                                ->attribute('label', 'Ensures filenames do not contain underscores.')
                                                ->defaultValue(false)
                                            ->end()
                                        ->end()
                                    ->end()
                                    ->arrayNode('formatting')
                                        ->addDefaultsIfNotSet()
                                        ->attribute('type', 'choice')
                                        ->children()
                                            ->booleanNode('multiple_statement_alignment_sniff')
                                                ->attribute('label', 'Checks alignment of assignments. If there are multiple adjacent assignments, it will check that the equals signs of each assignment are aligned. It will display a warning to advise that the signs should be aligned.')
                                                ->defaultValue(false)
                                            ->end()
                                        ->end()
                                    ->end()
                                    ->arrayNode('functions')
                                        ->addDefaultsIfNotSet()
                                        ->attribute('type', 'choice')
                                        ->children()
                                            ->booleanNode('function_call_signature_sniff')
                                                ->attribute('label', 'Enforces WordPress function call format.')
                                                ->defaultValue(false)
                                            ->end()
                                            ->booleanNode('function_declaration_argument_spacing_sniff')
                                                ->attribute('label', 'Enforces WordPress function argument spacing.')
                                                ->defaultValue(false)
                                            ->end()
                                        ->end()
                                    ->end()
                                    ->arrayNode('naming_conventions')
                                        ->addDefaultsIfNotSet()
                                        ->attribute('type', 'choice')
                                        ->children()
                                            ->booleanNode('valid_function_name_sniff')
                                                ->attribute('label', 'Enforces WordPress function name format.')
                                                ->defaultValue(false)
                                            ->end()
                                        ->end()
                                    ->end()
                                    ->arrayNode('objects')
                                        ->addDefaultsIfNotSet()
                                        ->attribute('type', 'choice')
                                        ->children()
                                            ->booleanNode('object_instantiation_sniff')
                                                ->attribute('label', 'Ensures objects are assigned to a variable when instantiated.')
                                                ->defaultValue(false)
                                            ->end()
                                        ->end()
                                    ->end()
                                    ->arrayNode('php')
                                        ->addDefaultsIfNotSet()
                                        ->attribute('type', 'choice')
                                        ->children()
                                            ->booleanNode('discouraged_functions_sniff')
                                                ->attribute('label', 'Discourages the use of debug functions and suggests deprecated WordPress alternatives.')
                                                ->defaultValue(false)
                                            ->end()
                                        ->end()
                                    ->end()
                                    ->arrayNode('strings')
                                        ->addDefaultsIfNotSet()
                                        ->attribute('type', 'choice')
                                        ->children()
                                            ->booleanNode('double_quote_usage_sniff')
                                                ->attribute('label', 'Makes sure that any use of Double Quotes are warranted.')
                                                ->defaultValue(false)
                                            ->end()
                                        ->end()
                                    ->end()
                                    ->arrayNode('white_space')
                                        ->addDefaultsIfNotSet()
                                        ->attribute('type', 'choice')
                                        ->children()
                                            ->booleanNode('control_structure_spacing_sniff')
                                                ->attribute('label', 'Enforces spacing around logical operators and assignments.')
                                                ->defaultValue(false)
                                            ->end()
                                            ->booleanNode('operator_spacing_sniff')
                                                ->attribute('label', 'Modified version of Squiz operator white spacing.')
                                                ->defaultValue(false)
                                            ->end()
                                            ->booleanNode('php_indent_sniff')
                                                ->attribute('label', 'Enforces PHP indentation.')
                                                ->defaultValue(false)
                                            ->end()
                                        ->end()
                                    ->end()
                                    ->arrayNode('xss')
                                        ->addDefaultsIfNotSet()
                                        ->attribute('type', 'choice')
                                        ->children()
                                            ->booleanNode('escape_output_sniff')
                                                ->attribute('label', 'Enforce output escaping.')
                                                ->defaultValue(false)
                                            ->end()
                                        ->end()
                                    ->end()
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;
    }

    public function analyze(Project $project, File $file)
    {
        $config = $project->getFileConfig($file);
        $cmd = $project->getGlobalConfig('command');

        $standardsDir = null;
        if ($config['standard'] === 'custom') {
            if (isset($config['ruleset'])) {
                $cmd .= ' --standard='.escapeshellarg($config['ruleset']);
            } else {
                $standardsDir = tempnam(sys_get_temp_dir(), 'cs-ruleset');
                unlink($standardsDir);
                if (false === @mkdir($standardsDir, 0777, true)) {
                    throw new \RuntimeException('Could not create standards dir.');
                }

                $this->createRuleset($standardsDir, $project, $file);
                $cmd .= ' --standard='.escapeshellarg($standardsDir);
            }
        } else {
            $cmd .= ' --standard='.escapeshellarg($config['standard']);
        }

        $cmd .= ' --tab-width='.$config['tab_width'];
        $cmd .= ' --encoding='.$config['encoding'];

        $outputFile = tempnam(sys_get_temp_dir(), 'phpcs');
        $cmd .= ' --report-checkstyle='.escapeshellarg($outputFile);

        $inputFile = tempnam(sys_get_temp_dir(), 'phpcs');
        file_put_contents($inputFile, $file->getContent());
        $cmd .= ' '.escapeshellarg($inputFile);

        $proc = new Process($cmd, $project->getDir());
        $proc->setTimeout(300);
        $proc->run();

        $result = file_get_contents($outputFile);

        unlink($outputFile);
        unlink($inputFile);
        if (null !== $standardsDir) {
            unlink($standardsDir.'/ruleset.xml');
            rmdir($standardsDir);
        }

        if ($proc->getExitCode() > 1) {
            throw new ProcessFailedException($proc);
        }

        if (empty($result)) {
            return;
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

    private function createRuleset($standardsDir, Project $project, File $file)
    {
        $rulesetTemplate = <<<'TEMPLATE'
<?xml version="1.0"?>
<ruleset name="Scrutinizer Auto-Generated Standard">

    <description>Auto-generated by Scrutinizer</description>

%s
</ruleset>
TEMPLATE;

        $enabledSniffs = array();
        $this->flattenEnabledSniffs($enabledSniffs, $project->getFileConfig($file, 'sniffs'));

        if (empty($enabledSniffs)) {
            throw new \RuntimeException('The custom Coding Standard was chosen, but no sniffs were enabled.');
        }

        $rules = array();
        foreach ($enabledSniffs as $sniff) {
            $rules[] = '    <rule ref="'.$sniff.'" />';
        }

        $ruleset = sprintf($rulesetTemplate, implode("\n", $rules));
        file_put_contents($standardsDir.'/ruleset.xml', $ruleset);
    }

    private function flattenEnabledSniffs(array &$enabledSniffs, array $groupedSniffs, array $currentPath = array())
    {
        foreach ($groupedSniffs as $k => $v) {
            $currentPath[] = $k;

            if (is_array($v)) {
                $this->flattenEnabledSniffs($enabledSniffs, $v, $currentPath);
            } else if ($v === true) {
                $enabledSniffs[] = $this->getSniffName(implode('.', $currentPath));
            }

            array_pop($currentPath);
        }
    }

    private function getSniffName($configPath)
    {
        static $mapping = array(
            'psr1.classes.class_declaration_sniff' => 'PSR1.Classes.ClassDeclaration',
            'psr1.files.side_effects_sniff' => 'PSR1.Files.SideEffects',
            'generic.code_analysis.unused_function_parameter_sniff' => 'Generic.CodeAnalysis.UnusedFunctionParameter',
            'generic.code_analysis.for_loop_with_test_function_call_sniff' => 'Generic.CodeAnalysis.ForLoopWithTestFunctionCall',
            'generic.code_analysis.unconditional_if_statement_sniff' => 'Generic.CodeAnalysis.UnconditionalIfStatement',
            'generic.code_analysis.empty_statement_sniff' => 'Generic.CodeAnalysis.EmptyStatement',
            'generic.code_analysis.unnecessary_final_modifier_sniff' => 'Generic.CodeAnalysis.UnnecessaryFinalModifier',
            'generic.code_analysis.for_loop_should_be_while_loop_sniff' => 'Generic.CodeAnalysis.ForLoopShouldBeWhileLoop',
            'generic.code_analysis.useless_overriding_method_sniff' => 'Generic.CodeAnalysis.UselessOverridingMethod',
            'generic.code_analysis.jumbled_incrementer_sniff' => 'Generic.CodeAnalysis.JumbledIncrementer',
            'generic.classes.duplicate_class_name_sniff' => 'Generic.Classes.DuplicateClassName',
            'generic.white_space.disallow_tab_indent_sniff' => 'Generic.WhiteSpace.DisallowTabIndent',
            'generic.white_space.scope_indent_sniff' => 'Generic.WhiteSpace.ScopeIndent',
            'generic.white_space.disallow_space_indent_sniff' => 'Generic.WhiteSpace.DisallowSpaceIndent',
            'generic.php.disallow_short_open_tag_sniff' => 'Generic.PHP.DisallowShortOpenTag',
            'generic.php.sapi_usage_sniff' => 'Generic.PHP.SAPIUsage',
            'generic.php.no_silenced_errors_sniff' => 'Generic.PHP.NoSilencedErrors',
            'generic.php.deprecated_functions_sniff' => 'Generic.PHP.DeprecatedFunctions',
            'generic.php.upper_case_constant_sniff' => 'Generic.PHP.UpperCaseConstant',
            'generic.php.closing_php_tag_sniff' => 'Generic.PHP.ClosingPHPTag',
            'generic.php.forbidden_functions_sniff' => 'Generic.PHP.ForbiddenFunctions',
            'generic.php.lower_case_constant_sniff' => 'Generic.PHP.LowerCaseConstant',
            'generic.php.character_before_php_opening_tag_sniff' => 'Generic.PHP.CharacterBeforePHPOpeningTag',
            'generic.php.lower_case_keyword_sniff' => 'Generic.PHP.LowerCaseKeyword',
            'generic.formatting.multiple_statement_alignment_sniff' => 'Generic.Formatting.MultipleStatementAlignment',
            'generic.formatting.no_space_after_cast_sniff' => 'Generic.Formatting.NoSpaceAfterCast',
            'generic.formatting.space_after_cast_sniff' => 'Generic.Formatting.SpaceAfterCast',
            'generic.formatting.disallow_multiple_statements_sniff' => 'Generic.Formatting.DisallowMultipleStatements',
            'generic.functions.function_call_argument_spacing_sniff' => 'Generic.Functions.FunctionCallArgumentSpacing',
            'generic.functions.opening_function_brace_kernighan_ritchie_sniff' => 'Generic.Functions.OpeningFunctionBraceKernighanRitchie',
            'generic.functions.opening_function_brace_bsd_allman_sniff' => 'Generic.Functions.OpeningFunctionBraceBsdAllman',
            'generic.functions.call_time_pass_by_reference_sniff' => 'Generic.Functions.CallTimePassByReference',
            'generic.files.one_interface_per_file_sniff' => 'Generic.Files.OneInterfacePerFile',
            'generic.files.end_file_newline_sniff' => 'Generic.Files.EndFileNewline',
            'generic.files.line_length_sniff' => 'Generic.Files.LineLength',
            'generic.files.inline_html_sniff' => 'Generic.Files.InlineHTML',
            'generic.files.byte_order_mark_sniff' => 'Generic.Files.ByteOrderMark',
            'generic.files.end_file_no_newline_sniff' => 'Generic.Files.EndFileNoNewline',
            'generic.files.one_class_per_file_sniff' => 'Generic.Files.OneClassPerFile',
            'generic.files.line_endings_sniff' => 'Generic.Files.LineEndings',
            'generic.version_control.subversion_properties_sniff' => 'Generic.VersionControl.SubversionProperties',
            'generic.commenting.fixme_sniff' => 'Generic.Commenting.Fixme',
            'generic.commenting.todo_sniff' => 'Generic.Commenting.Todo',
            'generic.control_structures.inline_control_structure_sniff' => 'Generic.ControlStructures.InlineControlStructure',
            'generic.strings.unnecessary_string_concat_sniff' => 'Generic.Strings.UnnecessaryStringConcat',
            'generic.naming_conventions.camel_caps_function_name_sniff' => 'Generic.NamingConventions.CamelCapsFunctionName',
            'generic.naming_conventions.constructor_name_sniff' => 'Generic.NamingConventions.ConstructorName',
            'generic.naming_conventions.upper_case_constant_name_sniff' => 'Generic.NamingConventions.UpperCaseConstantName',
            'generic.metrics.cyclomatic_complexity_sniff' => 'Generic.Metrics.CyclomaticComplexity',
            'generic.metrics.nesting_level_sniff' => 'Generic.Metrics.NestingLevel',
            'zend.debug.code_analyzer_sniff' => 'Zend.Debug.CodeAnalyzer',
            'zend.files.closing_tag_sniff' => 'Zend.Files.ClosingTag',
            'zend.naming_conventions.valid_variable_name_sniff' => 'Zend.NamingConventions.ValidVariableName',
            'squiz.scope.static_this_usage_sniff' => 'Squiz.Scope.StaticThisUsage',
            'squiz.scope.method_scope_sniff' => 'Squiz.Scope.MethodScope',
            'squiz.scope.member_var_scope_sniff' => 'Squiz.Scope.MemberVarScope',
            'squiz.code_analysis.empty_statement_sniff' => 'Squiz.CodeAnalysis.EmptyStatement',
            'squiz.classes.lowercase_class_keywords_sniff' => 'Squiz.Classes.LowercaseClassKeywords',
            'squiz.classes.valid_class_name_sniff' => 'Squiz.Classes.ValidClassName',
            'squiz.classes.class_file_name_sniff' => 'Squiz.Classes.ClassFileName',
            'squiz.classes.self_member_reference_sniff' => 'Squiz.Classes.SelfMemberReference',
            'squiz.classes.class_declaration_sniff' => 'Squiz.Classes.ClassDeclaration',
            'squiz.arrays.array_bracket_spacing_sniff' => 'Squiz.Arrays.ArrayBracketSpacing',
            'squiz.arrays.array_declaration_sniff' => 'Squiz.Arrays.ArrayDeclaration',
            'squiz.objects.object_instantiation_sniff' => 'Squiz.Objects.ObjectInstantiation',
            'squiz.white_space.logical_operator_spacing_sniff' => 'Squiz.WhiteSpace.LogicalOperatorSpacing',
            'squiz.white_space.language_construct_spacing_sniff' => 'Squiz.WhiteSpace.LanguageConstructSpacing',
            'squiz.white_space.operator_spacing_sniff' => 'Squiz.WhiteSpace.OperatorSpacing',
            'squiz.white_space.control_structure_spacing_sniff' => 'Squiz.WhiteSpace.ControlStructureSpacing',
            'squiz.white_space.function_opening_brace_space_sniff' => 'Squiz.WhiteSpace.FunctionOpeningBraceSpace',
            'squiz.white_space.function_spacing_sniff' => 'Squiz.WhiteSpace.FunctionSpacing',
            'squiz.white_space.superfluous_whitespace_sniff' => 'Squiz.WhiteSpace.SuperfluousWhitespace',
            'squiz.white_space.member_var_spacing_sniff' => 'Squiz.WhiteSpace.MemberVarSpacing',
            'squiz.white_space.scope_closing_brace_sniff' => 'Squiz.WhiteSpace.ScopeClosingBrace',
            'squiz.white_space.scope_keyword_spacing_sniff' => 'Squiz.WhiteSpace.ScopeKeywordSpacing',
            'squiz.white_space.function_closing_brace_space_sniff' => 'Squiz.WhiteSpace.FunctionClosingBraceSpace',
            'squiz.white_space.semicolon_spacing_sniff' => 'Squiz.WhiteSpace.SemicolonSpacing',
            'squiz.white_space.cast_spacing_sniff' => 'Squiz.WhiteSpace.CastSpacing',
            'squiz.white_space.object_operator_spacing_sniff' => 'Squiz.WhiteSpace.ObjectOperatorSpacing',
            'squiz.php.disallow_comparison_assignment_sniff' => 'Squiz.PHP.DisallowComparisonAssignment',
            'squiz.php.disallow_size_functions_in_loops_sniff' => 'Squiz.PHP.DisallowSizeFunctionsInLoops',
            'squiz.php.heredoc_sniff' => 'Squiz.PHP.Heredoc',
            'squiz.php.disallow_ob_end_flush_sniff' => 'Squiz.PHP.DisallowObEndFlush',
            'squiz.php.inner_functions_sniff' => 'Squiz.PHP.InnerFunctions',
            'squiz.php.forbidden_functions_sniff' => 'Squiz.PHP.ForbiddenFunctions',
            'squiz.php.eval_sniff' => 'Squiz.PHP.Eval',
            'squiz.php.lowercase_p_h_p_functions_sniff' => 'Squiz.PHP.LowercasePHPFunctions',
            'squiz.php.discouraged_functions_sniff' => 'Squiz.PHP.DiscouragedFunctions',
            'squiz.php.embedded_php_sniff' => 'Squiz.PHP.EmbeddedPhp',
            'squiz.php.commented_out_code_sniff' => 'Squiz.PHP.CommentedOutCode',
            'squiz.php.disallow_inline_if_sniff' => 'Squiz.PHP.DisallowInlineIf',
            'squiz.php.disallow_multiple_assignments_sniff' => 'Squiz.PHP.DisallowMultipleAssignments',
            'squiz.php.global_keyword_sniff' => 'Squiz.PHP.GlobalKeyword',
            'squiz.php.non_executable_code_sniff' => 'Squiz.PHP.NonExecutableCode',
            'squiz.formatting.operator_bracket_sniff' => 'Squiz.Formatting.OperatorBracket',
            'squiz.functions.lowercase_function_keywords_sniff' => 'Squiz.Functions.LowercaseFunctionKeywords',
            'squiz.functions.global_function_sniff' => 'Squiz.Functions.GlobalFunction',
            'squiz.functions.function_duplicate_argument_sniff' => 'Squiz.Functions.FunctionDuplicateArgument',
            'squiz.functions.multi_line_function_declaration_sniff' => 'Squiz.Functions.MultiLineFunctionDeclaration',
            'squiz.functions.function_declaration_argument_spacing_sniff' => 'Squiz.Functions.FunctionDeclarationArgumentSpacing',
            'squiz.functions.function_declaration_sniff' => 'Squiz.Functions.FunctionDeclaration',
            'squiz.files.file_extension_sniff' => 'Squiz.Files.FileExtension',
            'squiz.commenting.inline_comment_sniff' => 'Squiz.Commenting.InlineComment',
            'squiz.commenting.post_statement_comment_sniff' => 'Squiz.Commenting.PostStatementComment',
            'squiz.commenting.class_comment_sniff' => 'Squiz.Commenting.ClassComment',
            'squiz.commenting.doc_comment_alignment_sniff' => 'Squiz.Commenting.DocCommentAlignment',
            'squiz.commenting.block_comment_sniff' => 'Squiz.Commenting.BlockComment',
            'squiz.commenting.function_comment_sniff' => 'Squiz.Commenting.FunctionComment',
            'squiz.commenting.function_comment_throw_tag_sniff' => 'Squiz.Commenting.FunctionCommentThrowTag',
            'squiz.commenting.variable_comment_sniff' => 'Squiz.Commenting.VariableComment',
            'squiz.commenting.empty_catch_comment_sniff' => 'Squiz.Commenting.EmptyCatchComment',
            'squiz.commenting.file_comment_sniff' => 'Squiz.Commenting.FileComment',
            'squiz.commenting.long_condition_closing_comment_sniff' => 'Squiz.Commenting.LongConditionClosingComment',
            'squiz.commenting.closing_declaration_comment_sniff' => 'Squiz.Commenting.ClosingDeclarationComment',
            'squiz.control_structures.control_signature_sniff' => 'Squiz.ControlStructures.ControlSignature',
            'squiz.control_structures.lowercase_declaration_sniff' => 'Squiz.ControlStructures.LowercaseDeclaration',
            'squiz.control_structures.inline_if_declaration_sniff' => 'Squiz.ControlStructures.InlineIfDeclaration',
            'squiz.control_structures.for_each_loop_declaration_sniff' => 'Squiz.ControlStructures.ForEachLoopDeclaration',
            'squiz.control_structures.for_loop_declaration_sniff' => 'Squiz.ControlStructures.ForLoopDeclaration',
            'squiz.control_structures.switch_declaration_sniff' => 'Squiz.ControlStructures.SwitchDeclaration',
            'squiz.control_structures.else_if_declaration_sniff' => 'Squiz.ControlStructures.ElseIfDeclaration',
            'squiz.strings.echoed_strings_sniff' => 'Squiz.Strings.EchoedStrings',
            'squiz.strings.concatenation_spacing_sniff' => 'Squiz.Strings.ConcatenationSpacing',
            'squiz.strings.double_quote_usage_sniff' => 'Squiz.Strings.DoubleQuoteUsage',
            'squiz.naming_conventions.valid_function_name_sniff' => 'Squiz.NamingConventions.ValidFunctionName',
            'squiz.naming_conventions.valid_variable_name_sniff' => 'Squiz.NamingConventions.ValidVariableName',
            'squiz.naming_conventions.constant_case_sniff' => 'Squiz.NamingConventions.ConstantCase',
            'squiz.operators.increment_decrement_usage_sniff' => 'Squiz.Operators.IncrementDecrementUsage',
            'squiz.operators.valid_logical_operators_sniff' => 'Squiz.Operators.ValidLogicalOperators',
            'squiz.operators.comparison_operator_usage_sniff' => 'Squiz.Operators.ComparisonOperatorUsage',
            'my_source.php.return_function_value_sniff' => 'MySource.PHP.ReturnFunctionValue',
            'my_source.php.eval_object_factory_sniff' => 'MySource.PHP.EvalObjectFactory',
            'my_source.debug.debug_code_sniff' => 'MySource.Debug.DebugCode',
            'my_source.commenting.function_comment_sniff' => 'MySource.Commenting.FunctionComment',
            'psr2.classes.property_declaration_sniff' => 'PSR2.Classes.PropertyDeclaration',
            'psr2.classes.class_declaration_sniff' => 'PSR2.Classes.ClassDeclaration',
            'psr2.methods.method_declaration_sniff' => 'PSR2.Methods.MethodDeclaration',
            'psr2.namespaces.namespace_declaration_sniff' => 'PSR2.Namespaces.NamespaceDeclaration',
            'psr2.namespaces.use_declaration_sniff' => 'PSR2.Namespaces.UseDeclaration',
            'psr2.files.end_file_newline_sniff' => 'PSR2.Files.EndFileNewline',
            'psr2.control_structures.control_structure_spacing_sniff' => 'PSR2.ControlStructures.ControlStructureSpacing',
            'psr2.control_structures.switch_declaration_sniff' => 'PSR2.ControlStructures.SwitchDeclaration',
            'psr2.control_structures.else_if_declaration_sniff' => 'PSR2.ControlStructures.ElseIfDeclaration',
            'pear.classes.class_declaration_sniff' => 'PEAR.Classes.ClassDeclaration',
            'pear.white_space.object_operator_indent_sniff' => 'PEAR.WhiteSpace.ObjectOperatorIndent',
            'pear.white_space.scope_indent_sniff' => 'PEAR.WhiteSpace.ScopeIndent',
            'pear.white_space.scope_closing_brace_sniff' => 'PEAR.WhiteSpace.ScopeClosingBrace',
            'pear.formatting.multi_line_assignment_sniff' => 'PEAR.Formatting.MultiLineAssignment',
            'pear.functions.function_call_signature_sniff' => 'PEAR.Functions.FunctionCallSignature',
            'pear.functions.function_declaration_sniff' => 'PEAR.Functions.FunctionDeclaration',
            'pear.functions.valid_default_value_sniff' => 'PEAR.Functions.ValidDefaultValue',
            'pear.files.including_file_sniff' => 'PEAR.Files.IncludingFile',
            'pear.commenting.inline_comment_sniff' => 'PEAR.Commenting.InlineComment',
            'pear.commenting.class_comment_sniff' => 'PEAR.Commenting.ClassComment',
            'pear.commenting.function_comment_sniff' => 'PEAR.Commenting.FunctionComment',
            'pear.commenting.file_comment_sniff' => 'PEAR.Commenting.FileComment',
            'pear.control_structures.control_signature_sniff' => 'PEAR.ControlStructures.ControlSignature',
            'pear.control_structures.multi_line_condition_sniff' => 'PEAR.ControlStructures.MultiLineCondition',
            'pear.naming_conventions.valid_function_name_sniff' => 'PEAR.NamingConventions.ValidFunctionName',
            'pear.naming_conventions.valid_variable_name_sniff' => 'PEAR.NamingConventions.ValidVariableName',
            'pear.naming_conventions.valid_class_name_sniff' => 'PEAR.NamingConventions.ValidClassName',
            'wordpress.arrays.array_declaration_sniff' => 'WordPress.Arrays.ArrayDeclaration',
            'wordpress.classes.valid_class_name_sniff' => 'WordPress.Classes.ValidClassName',
            'wordpress.files.file_name_sniff' => 'WordPress.Files.FileName',
            'wordpress.formatting.multiple_statement_alignment_sniff' => 'WordPress.Formatting.MultipleStatementAlignment',
            'wordpress.functions.function_call_signature_sniff' => 'WordPress.Functions.FunctionCallSignature',
            'wordpress.functions.function_declaration_argument_spacing_sniff' => 'WordPress.Functions.FunctionDeclarationArgumentSpacing',
            'wordpress.naming_conventions.valid_function_name_sniff' => 'WordPress.NamingConventions.ValidFunctionName',
            'wordpress.objects.object_instantiation_sniff' => 'WordPress.Objects.ObjectInstantiation',
            'wordpress.php.discouraged_functions_sniff' => 'WordPress.PHP.DiscouragedFunctions',
            'wordpress.strings.double_quote_usage_sniff' => 'WordPress.Strings.DoubleQuoteUsage',
            'wordpress.white_space.control_structure_spacing_sniff' => 'WordPress.WhiteSpace.ControlStructureSpacing',
            'wordpress.white_space.operator_spacing_sniff' => 'WordPress.WhiteSpace.OperatorSpacing',
            'wordpress.white_space.php_indent_sniff' => 'WordPress.WhiteSpace.PhpIndent',
            'wordpress.xss.escape_output_sniff' => 'WordPress.XSS.EscapeOutput',
        );

        if ( ! isset($mapping[$configPath])) {
            throw new \RuntimeException(sprintf('The config path "%s" has no known sniff.', $configPath));
        }

        return $mapping[$configPath];
    }
}
