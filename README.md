Scrutinizer
===========

Scrutinizer runs static code analysis, and runtime inspectors on your code, gathers their results, and combines them
in a unified output format.

Installation
------------

After downloading, you need to install the vendors via [composer](https://getcomposer.org):

```
composer install --dev
```

Then, you can use the executable ``./bin/scrutinizer``.


Configuration
-------------

Scrutinizer uses a configuration file named ``.scrutinizer.yml`` which it expects in the root folder of your
project.

To enable a tool, a minimal configuration such as:

```
tools:
    js_hint: ~
```

is sufficient, and would enable [JsHint](http://www.jshint.com/). For a complete reference, see below.


Learn more about scrutinizer in [its documentation](https://scrutinizer-ci.com/docs).