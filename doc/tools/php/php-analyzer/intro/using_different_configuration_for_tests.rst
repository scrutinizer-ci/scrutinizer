Using Different Configuration for Tests
=======================================

Introduction
------------
Typically, you want to be a bit more lenient when it comes to tests. For once,
there might be fixtures which do not need to adhere to all of the coding-style
guidelines, and then you also might not want to have all the documentation
related checks, and fixes.

Configuration
-------------
Let's assume that you have your shared code in a folder named ``src``, and your
tests in a folder named ``tests``.

.. code-block :: yml

    # .scrutinizer.yml

    tools:
        php_analyzer:
            filter:
                paths: [src/*, tests/*]

            config:
                checkstyle: ~
                verify_php_doc_comments: ~
                doc_comment_fixes: ~

            path_configs:
                tests:
                    paths: [tests/*]
                    checkstyle: false
                    verify_php_doc_comments: false
                    doc_comment_fixes: false

The configuration that is in the ``config`` section is used for all files
where no configuration in the ``path_configs`` section applies. In this case, these
are all files in the ``src`` folder. Also note, that each configuration in the
``path_configs`` section is inheriting the default config. This allows you to place
any common configuration there.
