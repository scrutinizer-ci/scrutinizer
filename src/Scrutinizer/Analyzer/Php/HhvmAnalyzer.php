<?php

namespace Scrutinizer\Analyzer\Php;

use Guzzle\Inflection\Inflector;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Scrutinizer\Analyzer\AnalyzerInterface;
use Scrutinizer\Analyzer\ProjectIteratorFactory;
use Scrutinizer\Config\ConfigBuilder;
use Scrutinizer\Logger\LoggableProcess;
use Scrutinizer\Model\Comment;
use Scrutinizer\Model\File;
use Scrutinizer\Model\Project;
use Scrutinizer\Process\Process;
use Scrutinizer\Util\PathUtils;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;
use Symfony\Component\Process\Exception\ProcessFailedException;

/**
 * Runs Analyzer of the HHVM.
 *
 * @doc-path tools/php/hhvm/
 * @display-name PHP HHVM
 */
class HhvmAnalyzer implements AnalyzerInterface, LoggerAwareInterface
{
    use LoggerAwareTrait;

    private static $rules = array(
        'BadPHPIncludeFile' => array("The include ``%s`` could not be validated.", false, 'Checks for bad include files.'),
        'PHPIncludeFileNotFound' => array("The include file ``%s`` was not found.", false, 'Checks for non-existent include files.'),
        'UnknownClass' => array("The class ``%s`` could not be found. Did you maybe forget to install all vendors?", false, 'Checks for unknown classes as they prevent optimization (requires all vendors).'),
        'UnknownBaseClass' => array("The base class ``%s`` could not be found. Did you maybe forget to install all vendors?", false, 'Checks for unknown base classes as they prevent optimization (requires all vendors).'),
        'UnknownFunction' => array("The function ``%s`` could not be found. Did you maybe forget to install all vendors?", false, 'Checks for unknown functions as they prevent optimization (requires all vendors).'),
        'UseEvaluation' => array("The use of eval() is discouraged as it prevents more deeper analysis and optimization of your code.", false, 'Checks for eval statements as they prevent optimization.'),
        'UseUndeclaredVariable' => array("The variable ``%s`` is not declared.", true, 'Checks for undeclared variables.'),
        'UseUndeclaredGlobalVariable' => array("The global variable ``%s`` is not declared.", false, 'Checks for undeclared global variables.'),
        'UseUndeclaredConstant' => array("The constant ``%s`` is not declared.", true, 'Checks for undeclared constants.'),
        'UnknownObjectMethod' => array("The method ``%s`` is unknown. Did you install all vendors?", false, 'Checks for unknown methods (requires all vendors).'),
        'InvalidMagicMethod' => array("The magic method ``%s`` looks invalid.", true, 'Checks for invalid magic methods.'),
        'BadConstructorCall' => array("The constructor call ``%s`` looks invalid.", true, 'Checks for invalid constructor calls.'),
        'DeclaredVariableTwice' => array("The variable ``%s`` was declared twice.", true, 'Checks for duplicate variable declaration.'),
        'DeclaredConstantTwice' => array("The constant ``%s`` was declared twice.", true, 'Checks for duplicate constant definition.'),
        'BadDefine' => array("The define ``%s`` looks invalid.", true, 'Checks for invalid defines.'),
        'RequiredAfterOptionalParam' => array("The required parameter ``%s`` is placed after an optional one.", true, 'Checks whether there are required parameters after optional ones.'),
        'RedundantParameter' => array("The parameter ``%s`` is redundant.", true, 'Checks for redundant parameters.'),
        'TooFewArgument' => array("The call ``%s`` has too few arguments.", true, 'Checks for too few arguments in calls.'),
        'TooManyArgument' => array("The call ``%s`` has too many arguments.", true, 'Checks for too many arguments in calls.'),
        'BadArgumentType' => array("The argument ``%s`` has a bad type.", true, 'Checks for bad argument types.'),
        'StatementHasNoEffect' => array("The statement ``%s`` has no effect.", true, 'Checks for statements without effects.'),
        'UseVoidReturn' => array("A void return value is being used in ``%s``.", true, 'Checks for usage of void return types.'),
        'MissingObjectContext' => array('$this cannot be used in a static context.', true, 'Checks for usage of $this in a static context.'),
        'MoreThanOneDefault' => array("There is more than one default in this switch context.", true, 'Checks whether there are multiple default statements in switch contexts.'),
        'InvalidArrayElement' => array("The array element ``%s`` looks invalid.", true, 'Checks whether array elements are valid.'),
        'InvalidDerivation' => array("The inheritance ``%s`` seems invalid.", true, 'Checks inheritance hierarchy for validity.'),
        'InvalidOverride' => array("The override ``%s`` seems invalid.", true, 'Checks overrides for validity.'),
        'ReassignThis' => array('$this cannot be re-assigned.', true, 'Checks that $this is not re-assigned.'),
        'MissingAbstractMethodImpl' => array("The implementation of some abstract methods is missing: %s", true, 'Checks for missing implementations of abstract methods.'),
        'BadPassByReference' => array("The pass-by-reference ``%s`` looks invalid.", true, 'Checks for invalid pass-by-reference.'),
        'ConditionalClassLoading' => array("The class ``%s`` is conditionally loaded. This prevents more aggressive optimization.", true, 'Checks for conditional class loading as this prevents optimization.'),
        'GotoUndefLabel' => array("The GOTO-label ``%s`` is invalid.", true, 'Checks for invalid GOTO labels.'),
        'GotoInvalidBlock' => array('The GOTO block is invalid: ``%s``', true, 'Checks for invalid GOTO blocks.'),
        'AbstractProperty' => array("The attribute ``%s`` is abstract.", true, 'Checks for abstract attributes.'),
        'UnknownTrait' => array("One of the traits used in this class is unknown. Did you maybe forget to install all vendors?", false, 'Checks for unknown traits as they prevent optimization (requires all vendors).'),
        'MethodInMultipleTraits' => array("The method ``%s`` is declared in multiple traits.", true, 'Checks whether method are declared multiple times in different traits.'),
        'UnknownTraitMethod' => array("The trait method ``%s`` is unknown.", true, 'Checks for unknown trait methods as they prevent optimization (requires all vendors).'),
        'InvalidAccessModifier' => array("The access modifier ``%s`` is invalid.", true, 'Checks for invalid access modifiers.'),
        'CyclicDependentTraits' => array("There is a cyclic dependency between traits: ``%s``", true, 'Checks for cyclic dependencies between traits.'),
        'InvalidTraitStatement' => array("The trait statement ``%s`` is invalid.", true, 'Checks for invalid trait statements.'),
        'RedeclaredTrait' => array("The trait ``%s`` is declared twice.", true, 'Checks whether a trait has been declared twice.'),
        'InvalidInstantiation' => array("The instantiation ``%s`` is invalid.", true, 'Checks for invalid instantiations.'),
    );

    public function scrutinize(Project $project)
    {
        $inputListFile = $this->prepareInputListFile($project);
        $outputDir = $this->prepareOutputDir();

        try {
            $this->runAnalyzer($project, $inputListFile, $outputDir);
            $this->processResult($project, $outputDir);

            $this->cleanUp($inputListFile, $outputDir);
        } catch (\Exception $ex) {
            $this->cleanUp($inputListFile, $outputDir);

            throw $ex;
        }
    }

    private function cleanUp($inputListFile, $outputDir)
    {
        @unlink($inputListFile);
        (new Filesystem())->remove($outputDir);
    }

    private function runAnalyzer(Project $project, $inputListFile, $outputDir)
    {
        $cmd = $this->buildCommand($project, $inputListFile, $outputDir);
        $proc = new LoggableProcess($cmd);
        $proc->setLogger($this->logger);
        $proc->setTimeout(1800);

        if (0 !== $proc->run()) {
            throw new ProcessFailedException($proc);
        }
    }

    private function processResult(Project $project, $outputDir)
    {
        $rs = json_decode(file_get_contents($outputDir.'/CodeError.js'), true);

        foreach ($rs[1] as $ruleName => $violations) {
            foreach ($violations as $violation) {
                $path = $this->getRelativePath($project->getDir(), $violation['c1'][0]);

                if (isset(self::$rules[$ruleName]) && ! $project->getFileConfig($path, $this->convertCase($ruleName))) {
                    continue;
                }

                if ( ! $project->isAnalyzed($path) || PathUtils::isFiltered($path, $project->getGlobalConfig('filter'))) {
                    continue;
                }

                $project->getFile($path)->map(function(File $file) use ($violation, $ruleName) {
                    $line = $violation['c1'][1];

                    if ( ! isset(self::$rules[$ruleName])) {
                        $file->addComment($line, new Comment(
                            $this->getName(),
                            $this->getName().'.'.$this->convertCase($ruleName),
                            sprintf('``%s`` violates rule ``%s``.', $violation['d'], $ruleName)
                        ));

                        return;
                    }

                    $file->addComment($line, new Comment(
                        $this->getName(),
                        $this->getName().'.'.$this->convertCase($ruleName),
                        sprintf(self::$rules[$ruleName][0], $violation['d'])
                    ));
                });
            }
        }
    }

    private function convertCase($camelCase)
    {
        $snake = Inflector::getDefault()->snake($camelCase);
        $snake = str_replace(
            array('p_hp_', 'ph_p'),
            array('php_', 'php_'),
            $snake
        );

        return $snake;
    }

    private function getRelativePath($projectDir, $absPath)
    {
        return substr($absPath, strlen($projectDir) + 1);
    }

    private function buildCommand(Project $project, $inputListFile, $outputDir)
    {
        $cmd = $project->getGlobalConfig('command');

        $cmd .= ' --hphp -t analyze';
        $cmd .= ' --input-list '.escapeshellarg($inputListFile);
        $cmd .= ' --output-dir '.escapeshellarg($outputDir);

        return $cmd;
    }

    private function prepareOutputDir()
    {
        $dir = tempnam(sys_get_temp_dir(), 'hhvm-output');
        unlink($dir);

        if (false === @mkdir($dir, 0777, true)) {
            throw new \RuntimeException(sprintf('Could not create output dir "%s".', $dir));
        }

        return $dir;
    }

    private function prepareInputListFile(Project $project)
    {
        $inputList = $this->buildInputList($project);

        $file = tempnam(sys_get_temp_dir(), 'hhvm-list');
        file_put_contents($file, implode("\n", $inputList));

        return $file;
    }

    private function buildInputList(Project $project)
    {
        $finder = Finder::create()->in($project->getDir())->files();
        foreach ($project->getGlobalConfig('extensions') as $extension) {
            $finder->name('*.'.$extension);
        }

        $inputList = array();
        foreach ($finder as $file) {
            /** @var SplFileInfo $file */

            $inputList[] = $file->getRealPath();
        }

        return $inputList;
    }

    public function buildConfig(ConfigBuilder $builder)
    {
        $builder
            ->info('Runs HHVM\'s analyses on your project.')
            ->globalConfig()
                ->scalarNode('command')
                    ->attribute('show_in_editor', false)
                    ->defaultValue('hhvm')
                ->end()
                ->arrayNode('extensions')
                    ->prototype('scalar')->end()
                    ->defaultValue(array('php'))
                ->end()
        ;

        $rulesNode = $builder->perFileConfig()
            ->attribute('type', 'choice')
            ->addDefaultsIfNotSet()
            ->children();

        foreach (self::$rules as $name => $data) {
            list($messageTemplate, $default, $info) = $data;

            $rulesNode
                ->booleanNode($this->convertCase($name))
                    ->info($info)
                    ->attribute('label', $info)
                    ->defaultValue($default)
                ->end();
        }
    }

    public function getMetrics()
    {
        return array();
    }

    public function getName()
    {
        return 'php_hhvm';
    }
}