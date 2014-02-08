Code Rating System
==================
Scrutinizer uses a rating algorithm for the different elements of your code such as classes, methods or functions
which combines their different metrics (such as complexity, coupling, cohesion, etc.) in a single rating score.

This allows you to track changes in metrics for your project very easily without verifying each individual metric,
but only diving in deeper when and where needed.

The rating is done with the grades A-F; A being the best rating and F the worst.

Configuration
-------------
The code rating algorithm uses data from different language-specific tools which must be enabled in your configuration.
Below you find the list of tools per language.

PHP
~~~
For PHP, data from the following tools is being used. At least one must be enabled in your build config:

- :doc:`PHP PDepend <tools/php/pdepend/index>` (complexity, size)
- :doc:`PHP Copy/Paste Detector <tools/php/copy-paste-detector/index>` (duplication)
- :doc:`PHP Analyzer <tools/php/php-analyzer/index>` (coupling, cohesion)

Others
~~~~~~
We always love to add support for more languages, contact us at support@scrutinizer-ci.com.
