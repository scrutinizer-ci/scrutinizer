PHP Code Sniffer Standards
==========================

The following PHP Code Sniffer Standards are already installed and can be configured via the configuration file.

Configuration
-------------

.. code-block :: yml

    # .scrutinizer.yml
    tools:
        php_code_sniffer:
            config:
                standard: "PSR1" # Other Values: PSR2, PEAR, Zend, WordPress, Drupal, TYPO3CMS

PSR1 *recommended for existing projects*
----------------------------------------
A basic coding standard from the PSR group that "(...) should be considered the standard coding elements that are
required to ensure a high level of technical interoperability between shared PHP code".

PSR2 *recommended for new projects*
-----------------------------------
PSR2 extends PSR1 and defines further rules with the goal to "(...) reduce cognitive friction when scanning code from
different authors (...) by enumerating a shared set of rules and expectations about how to format PHP code".

Drupal
------
The Drupal Coding Standard is a PHP Code Sniffer Standard which implements the official
`Drupal coding standards <https://drupal.org/coding-standards>`_.

Symfony
-------
If you are using Symfony, we recommend either the PSR1 standard (for existing projects) or the PSR2 standard (for new
projects). Alternatively, you can also define your own project standard based on these.

WordPress
---------
The WordPress Coding Standards relies on several rules which have already been defined for the Zend and PEAR coding
standards and further customizes them for WordPress needs with regard to:

- Brace usage
- Spaces around logic statements, assignments, functions, and classes
- No CamelCase function names
- Deprecated WordPress functions usage, with suggested alternatives
- Use of posix regular expressions
- Filenames with an underscore

Zend
----
The Zend standard is recommended for Zend Framework projects of version 1. For newer Zend Framework versions, we
suggest to consider using either PSR1 or PSR2 or creating your own standard. If you want to create your own standard,
we recommend to use our `build config editor <https://scrutinizer-ci.com/build-config-editor>`_.