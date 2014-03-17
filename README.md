# Scrutinizer

Scrutinizer runs static code analysis, and runtime inspectors on your code, gathers their results, and combines them
in a unified output format.

[![Scrutinizer Quality Score](https://scrutinizer-ci.com/g/scrutinizer-ci/scrutinizer/badges/quality-score.png?s=00b43441f630596431d776a2db52f4b2f532b037)](https://scrutinizer-ci.com/g/scrutinizer-ci/scrutinizer/)
[![Build Status](https://travis-ci.org/scrutinizer-ci/scrutinizer.png?branch=master)](https://travis-ci.org/scrutinizer-ci/scrutinizer)

## Installation

You can download a compiled phar file from [scrutinizer-ci.com/scrutinizer.phar](https://scrutinizer-ci.com/scrutinizer.phar).

After downloading, you can simply run scrutinizer with

```
php scrutinizer.phar
```

This will give you a nice output with all available sub-commands. Note that Scrutinizer requires PHP 5.4 or greater.

## Configuration

Scrutinizer uses a configuration file named ``.scrutinizer.yml`` which it expects in the root folder of your
project.

If you would like to run a build from scrutinizer-ci.com on your local PC, simply copy the resolved configuration to a
``.scrutinizer.yml`` file in your root folder.

Learn more about configuration in [the documentation](https://scrutinizer-ci.com/docs).

## Adding Support for an Analysis Tool

If you would like to add support for an analysis tool not yet supported by Scrutinizer, you can
do so in this repository very easily.

This library runs analysis tools, parses their results and converts them to a unified format which
is used for further processing. Adding support for another analysis tool requires adding an Analyzer
class which contains the available configuration options and knows how to parse the tool's output format.

As a starting point, you might want to have a look at the JSHint Analyzer which is relatively
simple and contains all the essential parts:
https://github.com/scrutinizer-ci/scrutinizer/blob/master/src/Scrutinizer/Analyzer/Javascript/JsHintAnalyzer.php

When you added an analyzer, make sure to register it in the Main class (https://github.com/scrutinizer-ci/scrutinizer/blob/master/src/Scrutinizer/Scrutinizer.php#L37) and please also
add some unit tests (see below for how to run the tests).


## Running Unit Tests

In order to run the unit tests, you need to have a variety of libraries and extensions installed.

### Composer

If you have not already installed PHP's dependency manager, [Composer](https://getcomposer.org), you need to download it
and make it available either locally or as a global install on your system.

```
$ curl -sS https://getcomposer.org/installer | sudo php -- --install-dir=/usr/bin
```


### NPM

Also make sure that you have JavaScript's dependency manager, npm, installed.

You can find more instructions on that here:
https://www.npmjs.org/doc/README.html


### Bundler

Also make sure that you have Ruby bundler installed.

You can find more instructions on that here:
http://bundler.io/


### Installing Project Dependencies

Simply run composer's, npm's and bundler's install commands, this will automatically download all the necessary dependencies and install
them locally in the directory:

```
$ composer install
$ npm install
$ bundle install
```


### Installing PHPUnit

Scrutinizer uses PHPUnit to run all unit tests. We need some additional system dependencies if you do not already have
the phpunit executable in your path.

Please follow the installation instructions here:
http://phpunit.de/manual/current/en/installation.html

### Install XDebug

XDebug is a PHP extension which must be installed on your system to allow us to test code coverage generation for PHP.
You can install it through pecl:

```
$ sudo pecl install xdebug
```

### Run the tests

Finally, you can now run the tests, by executing phpunit in the root folder:

```
$ phpunit
```
