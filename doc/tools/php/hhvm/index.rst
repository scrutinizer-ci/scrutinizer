PHP HHVM
========

    HHVM is a new open-source virtual machine designed for executing programs written in PHP.
    HHVM uses a just-in-time compilation approach to achieve superior performance while maintaining the flexibility and
    ease of use that PHP developers are accustomed to (dynamic features like eval(), rapid run-edit-debug cycle, etc).

    --- https://github.com/facebook/hhvm/wiki

Before compilation, the HHVM performs a detailed analysis of your source code. The results of this analysis can help you
find bugs and potential issues in your code. It's also a great way to check how compatible your code is with the HHVM.

.. tip ::
    We have chosen the default analysis rules in a way which produces good results without vendors installed. However,
    for optimal results, we recommend you to install all vendor files which are used by your project.

.. include :: php_hhvm_configuration.rst

.. include :: php_hhvm_configuration_reference.rst