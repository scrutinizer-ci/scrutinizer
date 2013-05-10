<?php

$projectDir = $_SERVER['argv'][1];
$changedPathsFile = $_SERVER['argv'][2];

if ( ! is_dir($projectDir)) {
    printf('Project Directory "%s" is not a directory.'.PHP_EOL, $projectDir);
}
if ( ! is_file($changedPathsFile)) {
    printf('Changed Paths File "%s" is not a file.'.PHP_EOL, $changedPathsFile);
}

$changedPaths = explode("\n", trim(file_get_contents($changedPathsFile)));
if ($changedPaths !== array('README.md', 'Bar.php')) {
    printf('Changed Paths did not contain array("README.md", "Bar.php"), but got: %s', file_get_contents($changedPathsFile));
}

if ( ! is_file($projectDir.'/README.md')) {
    printf('The project directory "%s" did not contain "README.md".', $projectDir);
}

echo json_encode(array(
    'metrics' => array(
        'benchmark' => 0.123,
    ),
));