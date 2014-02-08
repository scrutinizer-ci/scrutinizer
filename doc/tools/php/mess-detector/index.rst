PHP Mess Detector
=================

    [PHPMD] is a spin-off project of PHP Depend and aims to be a PHP equivalent of the well known Java tool PMD. PHPMD
    can be seen as an user friendly and easy to configure frontend for the raw metrics measured by PHP Depend.

    --- http://phpmd.org/


.. include :: php_mess_detector_configuration.rst

Using a Custom Ruleset
----------------------
If you already have made a custom ruleset file, you can use that with Scrutinizer too. A basic set-up might look
like the following:

.. code-block :: yml

    before_commands:
        - git clone https://github.com/your-login/your-phpmd-ruleset.git ../your-phpmd-ruleset/

    tools:
        php_mess_detector:
            config:
                ruleset: ../your-phpmd-ruleset/ruleset.xml

.. include :: php_mess_detector_configuration_reference.rst
