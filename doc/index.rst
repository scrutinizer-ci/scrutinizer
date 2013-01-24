Scrutinizer CI Documentation
============================

Getting Started
---------------
scrutinizer ci is a hosted continuous inspection service for open-source as well as private code.

It runs static code analysis tools, runtime inspectors, and can also run your very own checks in your favorite language.

Inspections can be triggered manually, through Git pushes, or also for GitHub pull requests. Depending on how an
inspection is triggered, scrutinizer does not scan your entire project, but only those parts that changed which provides
you with highly relevant information. Furthermore, scrutinizer ci employs algorithms to filter out noise that you have
previously ignored.

In the future, we also plan to add support for collecting code metrics. This can be information like how many classes
your project has, lines of code, but also opens up possibilities to track things like how your application's performance
has developed over-time, or how the amount of database queries changed. The latter can for example help you to identify
code that introduced certain performance degradations.

Supported Languages and Tools
-----------------------------
Below, is the list of languages that we support at the moment. In addition, you can also always run custom commands as
long as their output format is among our supported formats.

Finally, we always love to add built-in support for more languages. If we are missing your favorite language/tool, do
not hesitate to `contact us <https://github.com/scrutinizer-ci/scrutinizer/issues>`_.

.. toctree ::
    :glob:
    :titlesonly:

    tools/*/index

Configuration
-------------
Scrutinizer relies on Yaml to provide it with configuration data; it scans different locations for this data. Most of
the time, you either place a ``.scrutinizer.yml`` file in your repository, or if you have the same configuration for all
branches insert the configuration directly on the settings page.

Most tools allow you to specify a global configuration which is applicable to your entire project, and also to override
this global config for selected sub-paths. The general structure looks like this:

.. code-block :: yaml

    tools:
        tool-name:
            config:
                # Global Configuration goes here

            path_configs:
                -
                    paths: [some-dir/*]
                    config:
                        # Configuration for all files in some-dir/ goes here

In the different language sections, you find all the specific options which are available for each tool.
