PHP Analyzer
============

Introduction
------------
PHP Analyzer is our own premium analysis tool for PHP code. It's latest version is exclusively available through the
hosted version on scrutinizer-ci.com.

PHP Analyzer performs much of the same analyses of a compiler such as type inference, or other flow analyses.
These analyses provide a solid foundation for its sophisticated checks, reliable bug detection routines and robust
automated fixes.

Type Inference
--------------
PHP Analyzer performs type-inference on your code. In inferring types of your code, PHP Analyzer not only considers
the code itself (such as type-hints), but also takes into account widely used doc comments (such as ``@param``) where it
deems them valuable.

To ensure, your code is understood well by PHP Analyzer, you can read the following guide for what information is
considered by PHP Analyzer currently:

:doc:`Annotating Code with Doc Comments <guides/annotating_code>`

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