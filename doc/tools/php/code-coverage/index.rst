PHP Code Coverage
=================

.. tip ::
    If the set-up of your test-suite is complex and you continuously integrate your code, you can also send us
    :doc:`code coverage data from an external service </tools/external-code-coverage/index>`.

Runs PHPUnit to gather code coverage information, and displays the coverage results inline in the change-set.

.. include :: php_code_coverage_configuration.rst

Installing Dependencies
-----------------------
Often you will need to install the dependencies for your project before you can run the tests. If you are using composer,
you can do so easily by adding the following to your configuration:

.. code-block :: yaml

    before_commands:
        - "composer install --prefer-source"

We update the installed composer version every couple of hours.

PHPUnit Configuration
---------------------
By default, a ``phpunit.xml`` or ``phpunit.xml.dist`` file is expected in your project's root folder. However, if you need a
different configuration, you can change the test command:

.. code-block :: yaml

    tools:
        php_code_coverage:
            # A phpunit.xml/phpunit.xml.dist is expected in tests/
            test_command: phpunit -c tests/


.. include :: php_code_coverage_configuration_reference.rst
