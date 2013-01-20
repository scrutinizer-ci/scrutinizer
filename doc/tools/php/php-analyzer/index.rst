PHP Analyzer
============

Introduction
------------
PHP Analyzer is an advanced static code analysis tool for PHP. It performs intense dataflow analyses such as type
inference, variable reachability, live variable analysis and others to allow for more sophisticated checks, detect
bugs, or even auto-fix files.

- :doc:`Annotating Code with Doc Comments <intro/annotating_code>`
- :doc:`Using Different Configuration for Tests <intro/using_different_configuration_for_tests>`

Configuration
-------------
You can enable PHP Analyzer with the following configuration:

.. code-block :: yaml

    tools:
        php_analyzer: true


.. toctree ::
    :hidden:
    :glob:

    intro/*
    checks
    fixes

.. include :: checks_include.rst
.. include :: fixes_include.rst

