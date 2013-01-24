Custom Commands
===============

Scrutinizer allows you to run custom commands on each built. Each command can

- add comments/warnings to files
- suggest patches to files; for example to auto-fix styling issues.

Commands are run in fresh virtual machine installations. Your package data is located at
``/home/scrutinizer/build/package``.


.. include :: custom_configuration.rst

.. include :: custom_configuration_reference.rst