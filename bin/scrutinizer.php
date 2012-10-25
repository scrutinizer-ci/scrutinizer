<?php

use Scrutinizer\Cli\Application;

if ( ! is_file($autoloadFile = __DIR__.'/../vendor/autoload.php')) {
    echo 'Could not find autoload.php. Did you forget to run "composer install --dev"?'.PHP_EOL;
    exit(1);
}

require_once $autoloadFile;

$app = new Application();
$app->run();