PHP Code Coverage
=================

.. note ::
    Generating code coverage on our servers is only recommended for small libraries. If you need other services
    like MySQL, RabbitMQ, etc., we strongly recommend to send your
    :doc:`code coverage data from an external service </tools/external-code-coverage/index>`.

Runs PHPUnit to gather code coverage information, and displays the coverage results inline in the change-set. Code
coverage results are also used by our code rating algorithm.

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
