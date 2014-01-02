PHP Analyzer
============

Introduction
------------
PHP Analyzer is our own analysis tool for PHP code. It's latest version is exclusively available through the
hosted version on scrutinizer-ci.com.

PHP Analyzer performs much of the same analyses of a compiler such as type inference, or other flow analyses.
These analyses provide a solid foundation to perform reliable checks/bug detection and also allow PHP Analyzer to make
automated fixes for simple issues in your code.

Some additional readings for getting started with PHP Analyzer:

.. toctree ::
    :hidden:
    :glob:

    guides/*

- :doc:`Committing composer.lock for dependency analysis <guides/composer_dependencies>`
- :doc:`Enhancing type-inference via doc comments <guides/annotating_code>`

.. include :: php_analyzer_configuration.rst

For an overview of all options, see the :doc:`configuration reference <config_reference>`.

.. toctree ::
    :hidden:
    :glob:

    intro/*
    config_reference
    checks
    fixes
    metrics

.. include :: checks_include.rst
.. include :: fixes_include.rst
.. include :: metrics_include.rst