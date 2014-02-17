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


Unit Tests
==========

In order to run the unit tests, you need to have a variety of libraries and extensions installed.

Create a vendor folder
----------------------

```
$ mkdir vendor
```

Install Composer (https://getcomposer.org)
------------------------------------------

```
$ curl -sS https://getcomposer.org/installer | php -- --install-dir=vendor
$ php vendor/composer.phar install
```

Install Pear/Pecl (pecl.php.net)
--------------------------------

```
$ cd vendor
$ curl -O http://pear.php.net/go-pear.phar 
$ sudo php -d detect_unicode=0 go-pear.phar 
```
Follow the instructions. A helpful hint for those on Mac OS (http://jason.pureconcepts.net/2012/10/install-pear-pecl-mac-os-x/)

Install Xdebug (xdebug.org)
---------------------------

```
$ sudo pecl install xdebug
```

You will then need to configure your php.ini file. On the mac, it will be located in /etc/php.ini. If it is not there you will need
to copy the ```/etc/php.ini.default``` file to ```/etc.php```. Add the following lines to the end of the file:

```
[xdebug]
zend_extension=/usr/lib/php/extensions/no-debug-non-zts-<specific_to_your_environment>/xdebug.so
xdebug.file_link_format="txmt://open?url=file://%f&line=%1"
xdebug.remote_enable = On
xdebug.remote_autostart = 1
```

Restart apache

```
$ sudo apachectl restart
```

Install Node (NPM) (https://npmjs.org/)
---------------------------------------

Brew:
```
$ brew install node
```

Mac Ports:
```
$ port install npm
```


Install JSHint (www.jshint.com)
-------------------------------

```
$ cd vendor
$ npm install 
```

Now you will need to create a symlink to run jshint globally.

```
$ sudo ln -s $PWD/node_modules/jshint/bin/jshint /opt/local/bin/jshint
```

Run the tests
-------------

```
$ phpunit -c phpunit.xml.dist 

```
