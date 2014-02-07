PHP Code Sniffer
================

    PHP_CodeSniffer tokenises PHP, JavaScript and CSS files and detects violations of a defined set of coding standards.

    --- http://pear.php.net/package/PHP_CodeSniffer

.. include :: php_code_sniffer_configuration.rst

Using a Custom Ruleset
----------------------
If you already have made a custom ruleset file, you can use that with Scrutinizer too. A basic set-up might look
like the following:

.. code-block :: yml

    before_commands:
        - git clone https://github.com/your-login/your-coding-standard.git ../your-coding-standard/

    tools:
        php_code_sniffer:
            config:
                ruleset: ../your-coding-standard/some-standard.xml


.. include :: php_code_sniffer_configuration_reference.rst