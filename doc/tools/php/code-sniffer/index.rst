PHP Code Sniffer
================

    PHP_CodeSniffer tokenises PHP, JavaScript and CSS files and detects violations of a defined set of coding standards.

    --- http://pear.php.net/package/PHP_CodeSniffer

.. include :: php_code_sniffer_configuration.rst

Installing a Custom Standard
----------------------------
Scrutinizer has support for commonly used :doc:`PHP Code Sniffer Standards <standards>` built-in. However, if you would
like to install a custom standard, Scrutinizer supports that too. A basic set-up looks like the following:

.. code-block :: yml

    before_commands:
        - git clone https://github.com/your-login/your-coding-standard.git ../your-coding-standard/

    tools:
        php_code_sniffer:
            config:
                ruleset: ../your-coding-standard/some-standard.xml

.. include :: php_code_sniffer_configuration_reference.rst

.. toctree ::
    :hidden:

    standards.rst