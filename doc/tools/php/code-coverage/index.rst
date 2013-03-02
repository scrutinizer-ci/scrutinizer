PHP Code Coverage
=================

Runs PHPUnit to gather code coverage information, and displays the coverage results inline in the change-set.

.. include :: php_code_coverage_configuration.rst

Improving Performance on Bigger Projects
----------------------------------------
For bigger projects, generating code coverage information can take a long time. In order to speed up this process,
scrutinizer only runs unit tests which are affected by code changes. In addition, you can disable coverage generation
when your entire project is inspected with the ``only_changesets`` flag:

.. code-block :: yaml

    tools:
        php_code_coverage:
            only_changesets: true


.. include :: php_code_coverage_configuration_reference.rst