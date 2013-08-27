Configuration
=============

Locations
---------
All configuration for scrutinizer ci is written in Yaml. Scrutinizer looks in several locations for this configuration
(in this order):

1. Configuration submitted when manually scheduling
2. ``.scrutinizer.yml`` file in your repository
3. Repository Configuration
4. Global Configuration

Configuration Cascade
---------------------

Introduction
~~~~~~~~~~~~
By default, the first configuration that is present wins (ignoring other configurations). If you manage many repositories
however, you will find yourself repeating (almost) the same configuration over and over again. For such use cases, you
can set-up a global configuration which acts as a base, and then only overwrite selected parts of this global configuration.

Setting Up a Base Configuration
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
You can set-up a base configuration in your `profile <https://scrutinizer-ci.com/profile/build-configs>`_. Once you have
set-up a base configuration, you can select it on the settings page of your repository. Also, make sure to set the
``inherit`` flag to ``true``:

.. code-block :: yaml

    # inherit the next configuration down the chain (see above for the order)
    inherit: true

    tools:
        # Overwrite selected settings, but keep everything else as in the base configuration.


Tool Configuration Structure
----------------------------
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

In the different :doc:`language sections <index>`, you find all the specific options which are available for each tool.

Analyzed Files / File Filter
----------------------------
By default, Scrutinizer will inspect all files in your project directory. This might include files which you installed
(f.e. dependencies). You can fine tune the analyzed files, using the ``filter`` configuration:

.. code-block :: yaml

    filter:
        paths: [dir1/*, dir2/*]
        excluded_paths: [excluded_dir1/*, excluded_dir2/*]

All paths are defined relative to your root folder, and are checked in this order:

1. A file must match at least one path defined in the ``paths`` setting. If the ``paths`` setting is empty, this will be
   treated like if it would contain a single path ``*``; that is it would always match.

2. A file must not match a single path defined in the ``excluded_paths`` setting.

Some tools might provide additional options of filtering. These are mentioned in the respective documentation chapters
for these tools.