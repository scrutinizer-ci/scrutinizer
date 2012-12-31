Scrutinizer
===========

Scrutinizer runs various static analysis tools on your code, collects,
and displays the results for you. It can also be integrated into your CI set-up.

Installation
------------

After downloading, you need to install the vendors via [composer](https://getcomposer.org):

```
composer install --dev
```

Then, you can use the executable in ``bin/scrutinizer`` or alias it by placing a script in ``/usr/bin/scrutinizer``:

```
#!/bin/sh

/usr/bin/env php /path/to/bin/scrutinizer $*
```
