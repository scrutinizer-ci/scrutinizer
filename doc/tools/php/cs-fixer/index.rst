PHP CS Fixer
============

    The PSR-1 and PSR-2 Coding Standards fixer for your code.

    --- http://cs.sensiolabs.org/


.. include :: php_cs_fixer_configuration.rst

PSR1/PSR2 Fixes
---------------
If you would like to enable PSR1/PSR2 compliant fixes for your code, you can do so with
the following configuration:

.. code-block :: yaml

    # .scrutinizer.yml
    tools:
        php_cs_fixer:
            config: { level: psr2 } # or psr1 if you would just like to get fixes for PSR1


.. include :: php_cs_fixer_configuration_reference.rst