PHP Analyzer
============

Introduction
------------
PHP Analyzer is an advanced static code analysis tool for PHP. It performs intense dataflow analyses such as type
inference, variable reachability, live variable analysis and others to allow for more sophisticated checks, detect
bugs, or even auto-fix files.

- :doc:`Annotating Code with Doc Comments <intro/annotating_code>`
- :doc:`Using Different Configuration for Tests <intro/using_different_configuration_for_tests>`

.. include :: php_analyzer_configuration.rst


.. toctree ::
    :hidden:
    :glob:

    intro/*
    checks
    fixes

.. include :: checks_include.rst
.. include :: fixes_include.rst

.. include :: php_analyzer_configuration_reference.rst