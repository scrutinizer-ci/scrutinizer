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
