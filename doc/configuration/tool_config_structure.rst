Changing the Configuration for Certain Paths
--------------------------------------------
Each tool which has built-in support on Scrutinizer follows the same simple configuration structure. The general
structure looks like this:

.. code-block :: yaml

    tools:
        tool-name:
            config:
                # Default Configuration goes here

            path_configs:
                -
                    paths: [tests/*]
                    config:
                        # Configuration for all files in tests/ goes here

The structure below the ``config`` keys differs per analysis tool, you can find more information in the documentation
of the respective tool, see the :doc:`language sections </index>` for an overview of available tools. Most tools come
with a good default configuration which we suggest to try first.


