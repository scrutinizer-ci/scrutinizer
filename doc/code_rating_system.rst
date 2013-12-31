Code Rating System
==================
Scrutinizer uses a language agnostic algorithm to rate different elements of your code such as classes, methods, or
functions with grades A-F and also computes a weighted average for your entire project. These ratings are instrumental
in pinpointing the parts of your software that require your attention most and also allow to measure the progress of your
project both for developers and project managers easily.

Criteria and Scores
-------------------
In our rating system, we take into account common design problems which manifest themselves in duplicated, unclear or
complex code. These criteria are measured in the form of software metrics that we collect for your code (see below for
which analysis tools must be enabled for each language).

All code elements are rated on a scale from **0 (worst)** to **10 (best)**. Besides we also use the following grades:

+-----------------+---------------------+
| Class           | Interval            |
+=================+=====================+
| A               | [8, 10]             |
+-----------------+---------------------+
| B               | [6, 8)              |
+-----------------+---------------------+
| C               | [4, 6)              |
+-----------------+---------------------+
| D               | [2, 4)              |
+-----------------+---------------------+
| F               | [0, 2)              |
+-----------------+---------------------+

Configuration
-------------
The code rating algorithm uses data from different language-specific tools which must be enabled in your configuration.
Below you find the list of tools per language.

PHP
~~~
For PHP, data from the following tools is being used. At least one must be enabled in your build config:

- :doc:`PHP PDepend <tools/php/pdepend/index>`
- :doc:`PHP Copy/Paste Detector <tools/php/copy-paste-detector/index>`
- :doc:`PHP CodeCoverage <tools/php/code-coverage/index>`

Others
~~~~~~
Unfortunately, only PHP is supported at this point. If you would like to see another language, let us know at
support@scrutinizer-ci.com.
