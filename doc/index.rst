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

Languages
---------
Below, is the list of languages that we support at the moment. In addition, you can also always run custom commands as
long as their output is in one of our supported formats.

Finally, we always love to expand support to more languages, so please get in contact if we are missing your favorite
language at the moment.


- Javascript
- :doc:`PHP <tools/php/index>`


