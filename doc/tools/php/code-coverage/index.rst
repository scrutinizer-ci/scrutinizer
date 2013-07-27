PHP Code Coverage
=================

Runs PHPUnit to gather code coverage information, and displays the coverage results inline in the change-set.

.. include :: php_code_coverage_configuration.rst

Installing Dependencies
-----------------------
Often you will need to install the dependencies for your project before you can run the tests. If you are using composer,
you can do so by adding the following to your configuration:

.. code-block :: yaml

    before_commands:
        - "composer install --prefer-source"

PHPUnit Configuration
---------------------
By default, a ``phpunit.xml`` or ``phpunit.xml.dist`` file is expected in your project's root folder. If you need a
different configuration, you can change the test command:

.. code-block :: yaml

    tools:
        php_code_coverage:
            test_command: phpunit -c tests/my_config.xml


.. include :: php_code_coverage_configuration_reference.rst