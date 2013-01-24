Custom Commands
===============

Scrutinizer allows you to run custom commands on each built. Each command can

- add comments/warnings to files
- suggest patches for files; for example to auto-fix styling issues
- collect metrics (coming soon)

Commands are run in fresh virtual machine instances.

Configuration
-------------
The most basic configuration looks like this:

.. code-block :: yaml

    tools:
        custom_commands:
            -
                command: check.sh %pathname%
                output_format: scrutinizer_json

The place holder ``%pathname%`` is automatically replaced with the absolute path of each file that needs to be checked.

Supported Output Formats
------------------------
In the following, you find all supported output formats. If we are not yet supporting the output format of your favorite
tool, please `open an issue <https://github.com/scrutinizer-ci/scrutinizer/issues/new>`_.

Scrutinizer JSON
~~~~~~~~~~~~~~~~
This allows you to output a simple json structure:

.. code-block :: js

    {
        "comments": {
            "line-nb": [
                {
                    "id": "some-unique-id",
                    "message": "A human readable text which is later displayed, and which may have a {placeholder}",
                    "params": {
                        "placeholder": "some-value"
                    }
                },
                {
                    // Another comment on the same line.
                }
            ],
            "another-line-nb": [
                // More comments on another line.
            ]
        },

        "new-content": "the new content of the file"
    }

In general, it is recommended to not embed dynamic parts in the message, but instead use placeholders and specify
parameters. This will allow for some more sophisticated post processing, but is not required.

Logging
-------
By default, the result is expected to be sent to STDOUT. However, if you also would like to log progress so that you
can watch it on the website, you can also set a specific output file. In this case, everything sent to STDOUT would be
directly streamed to the progress log on the website.

.. code-block :: yaml

    tools:
        custom_commands:
            -
                # ... see above
                output_file: some-file-output.json

Installing Dependencies
-----------------------
If your custom command requires some set-up before it can perform its checks, you can either let it do this set-up
itself, or move these commands to the ``before_commands`` section. The latter is generally preferable:

.. code-block :: yaml

    before_commands:
        - composer install

    tools:
        custom_commands:
            -
                # ...
