Configuration
=============

Locations
---------
All configuration for Scrutinizer is written in Yaml. Scrutinizer looks in several locations for this configuration
(in this order):

1. Configuration submitted when manually scheduling
2. ``.scrutinizer.yml`` file in your repository (**most flexibility**)
3. Repository Configuration (**for beginners**)
4. Global Configuration

Scrutinizer uses an configuration cascade where you can set-up a single configuration for multiple projects
and overwrite certain settings for one or another project (see the :doc:`configuration/cascade` chapter).


Getting Started with Default Configuration
------------------------------------------
If you would like to get started quickly, we provide several
`default configurations <https://github.com/scrutinizer-ci/scrutinizer/tree/master/res/default-configs>`_ from which
we automatically choose what seems most appropriate when you add a repository. Instead of creating a new configuration,
you can import the default configuration and just opt to overwrite certain settings:

.. code-block :: yaml

    # .scrutinizer.yml
    imports:
        - javascript
        - php

    ## Overwrite as needed here.

.. tip ::
    If you would like to share a configuration for your project, simply open a pull request/issue on
    `scrutinizer-ci/scrutinizer <https://github.com/scrutinizer-ci/scrutinizer>`_.


Further Reading
---------------

.. toctree ::
    :maxdepth: 1

    configuration/filter
    configuration/cascade
    configuration/tool_config_structure
    configuration/build_status