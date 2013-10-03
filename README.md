Scrutinizer
===========
Scrutinizer runs static code analysis, and runtime inspectors on your code, gathers their results, and combines them
in a unified output format.

[![Scrutinizer Quality Score](https://scrutinizer-ci.com/g/scrutinizer-ci/scrutinizer/badges/quality-score.png?s=00b43441f630596431d776a2db52f4b2f532b037)](https://scrutinizer-ci.com/g/scrutinizer-ci/scrutinizer/)

Installation
------------
You can download a compiled phar file from [scrutinizer-ci.com/scrutinizer.phar](https://scrutinizer-ci.com/scrutinizer.phar).

After downloading, you can simply run scrutinizer with

```
php scrutinizer.phar
```

This will give you a nice output with all available sub-commands. Note that Scrutinizer requires PHP 5.4 or greater.

Configuration
-------------
Scrutinizer uses a configuration file named ``.scrutinizer.yml`` which it expects in the root folder of your
project.

If you would like to run a build from scrutinizer-ci.com on your local PC, simply copy the resolved configuration to a
``.scrutinizer.yml`` file in your root folder.

Learn more about configuration in [the documentation](https://scrutinizer-ci.com/docs).
