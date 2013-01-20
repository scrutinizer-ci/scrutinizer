JS Hint
=======

    JSHint is a tool to detect errors and potential problems in JavaScript code and
    can be used to enforce coding conventions.

    --- http://www.jshint.com/

Configuration
-------------

You can enable JS Hint for your code by adding the following to your configuration file:

.. code-block :: yaml

    tools:
        js_hint: true

This basic configuration will execute JSHint on all files that end with ``.js`` in your project.