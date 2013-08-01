Code Rating System
==================
Scrutinizer uses a language agnostic algorithm to rate different elements of your code such as classes, methods, or
functions and also computes a weighted average for your entire project. These ratings are instrumental in pinpointing
the parts of your software that require your attention most and also allow to track the progress of your project both
for developers and project managers easily.

Criteria and Scores
-------------------
In our rating system, we take into account common design problems which manifest themselves in duplicated, unclear or
complex code. These criteria are measured in the form of software metrics that we collect for your code (see below for
which tools are used).

All code elements are rated on a scale from **0 (worst)** to **10 (best)**. Besides we also use the following classes:

+-----------------+---------------------+
| Class           | Interval            |
+=================+=====================+
| very good       | [8, 10]             |
+-----------------+---------------------+
| good            | [6, 8)              |
+-----------------+---------------------+
| satisfactory    | [4, 6)              |
+-----------------+---------------------+
| pass            | [2, 4)              |
+-----------------+---------------------+
| critical        | [0, 2)              |
+-----------------+---------------------+

Configuration
-------------
The rating algorithm requires different language-specific tools to be enabled in your build config as listed below.

PHP
~~~
For PHP, data from the following tools is being used. At least one must be enabled in your build config:

- :doc:`PHP PDepend <tools/php/pdepend/index>`
- :doc:`PHP CodeCoverage <tools/php/code-coverage/index>`

Others
~~~~~~
Unfortunately, only PHP is supported at this point. If you would like to see another language, let us know at
support@scrutinizer-ci.com.