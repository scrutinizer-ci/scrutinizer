Code Coverage Information
=========================
Scrutinizer also supports displaying code coverage information and metrics.

You can send us code coverage data from anywhere like for example from Travis or your Jenkins server. For small
libraries, it is also possible to generate the data directly on our servers.

1. From an external service (recommended)
-----------------------------------------

.. note ::
    This is the best option if you use Travis, Circle CI, codeship.io or operate your own Jenkins server.

Learn more about :doc:`sending Code Coverage from Travis, Jenkins & Co. <tools/external-code-coverage/index>`

------------------

2. On our servers (for small libraries only)
--------------------------------------------

.. note ::
    If you have dependencies on MySQL, RabbitMQ, etc. or need specific versions of runtime/VMs, consider using
    option 1) instead.

- :doc:`PHP Code Coverage <tools/php/code-coverage/index>`
