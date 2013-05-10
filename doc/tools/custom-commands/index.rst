Custom Commands
===============

Scrutinizer allows you to run custom commands on each build. Each command can

- add comments/warnings to files
- suggest patches for files; for example to auto-fix styling issues
- collect metrics

Commands are run in fresh virtual machine instances.

Scope of Analysis
-----------------
Before you can configure you own command, you need to decide on the scope that you would like the
command to analyze. Do you need to analyze the entire project at once, or is it enough to just analyze
each file separately? Generally, it is better to choose the narrower scope, i.e. file-based commands.

File-Based Commands
~~~~~~~~~~~~~~~~~~~

Configuration
^^^^^^^^^^^^^

.. code-block :: yaml

    tools:
        custom_commands:
            -
                scope: file
                command: check.sh %pathname% %fixed_pathname%

Available Placeholders:

| %pathname%       | The absolute path of the file that needs to be analyzed.          |
| %fixed_pathname% | The absolute path of the file after applying all created patches. |

In most cases, the content of both of these files will be identical unless a previous command has already proposed
changes to the file.

Expected Output
^^^^^^^^^^^^^^^
The output of the command is expected to be JSON with the following structure:

.. code-block :: js

    {
        "comments": [
            // First Comment
            {
                "line": 1,
                "id": "some-unique-id-for-the-comment",
                "message": "A human readable text which is later displayed, and which may have a {placeholder}",
                "params": {
                    "placeholder": "some-value"
                }
            },

            // Another Comment
            {
                // (see above)
            }
        ],

        "fixed_content": "New Content of the file if it should be changed, or omitted if it should not be changed."
    }

In general, it is recommended to not embed dynamic parts in the message, but instead use placeholders and specify
parameters. This will allow for some more sophisticated post processing, but is not required.

Project-Based Commands
~~~~~~~~~~~~~~~~~~~~~~

Configuration
^^^^^^^^^^^^^

.. code-block :: yaml

    tools:
        custom_commands:
            -
                scope: project
                command: check.sh %path% %changed_paths_file%

Available Placeholders:

| %path%               | The absolute path of the directory to the project's root              |
| %changed_paths_file% | The absolute path to the file which contains a list of changed paths. |

Expected Output Format
^^^^^^^^^^^^^^^^^^^^^^
The output of the command is to expected to be JSON with the following structure:

.. code-block :: js

    {
        "metrics": {
            "benchmark1-result": 0.234342
        }
    }

Logging
-------
By default, the result is expected to be sent to STDOUT. However, if you also would like to log progress so that you
can watch it on the website, you can also set a specific output file. In this case, everything sent to STDOUT is
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
