Defining the Build Status
=========================

By default, Scrutinizer will neither mark the build passed nor failed. You can change this behavior by specifying
failure conditions in your configuration.

.. tip ::
    Scrutinizer supports several :doc:`configuration locations </configuration>`. In this example, we
    assume that you are using a .scrutinizer.yml file, however the features work with other configuration
    locations equally well.

We use a easy to read DSL for defining failure conditions, let's take a look at a few examples:

.. code-block :: yaml

    # .scrutinizer.yml

    build_failure_conditions:
        - 'elements.rating(<= D).exists'               # No classes/methods with a rating of D or worse
        - 'elements.rating(<= D).new.exists'           # No new classes/methods with a rating of D or worse
                                                       # allowed (useful for legacy code)

        - 'issues.label("coding-style").exists'        # No coding style issues allowed
        - 'issues.label("coding-style").new.exists'    # No new coding style issues allowed

        - 'issues.label("coding-style").new.count > 5' # More than 5 new coding style issues.
        - 'issues.severity(>= MAJOR).new.exists'       # New issues of major or higher severity

        - 'project.metric("scrutinizer.quality", < 6)' # Code Quality Rating drops below 6
        - 'project.metric("scrutinizer.test_coverage", < 0.60)' # Code Coverage drops below 60%

If one of your failure conditions is satisfied, Scrutinizer will set the build status to failed (and passed otherwise).
In case, you are using GitHub, the status will automatically be set through the commit status API (and merged with your
continuous integration service if available).

Reference
---------

Condition Scopes
~~~~~~~~~~~~~~~~
The condition scope is the first part of each condition. In the above examples, we saw the scopes ``elements`` and
``issues``; here is a full reference of all available scopes:

:elements:   All code elements including f.e. classes, functions, methods, etc.
:classes:    All classes.
:operations: All functions and methods.
:issues:     All issues
:project:    Your project.

Filters
~~~~~~~
Filters are used to narrow down the initial scope further. In the example above, we already introduced some filters
like ``rating``, ``label`` or ``new``. Some filters like ``rating`` are not available for all scopes; for example, this
filter does not make sense on the ``issues`` scope.

Filters for issues
^^^^^^^^^^^^^^^^^^
These filters can only applied when using the ``issues`` scope.

:new: Only new issues.
:label: ``label("the-label-slug-here")`` filters out all issues which do **not** have this label (`available labels <https://scrutinizer-ci.com/docs/api/#index-issues>`_).
:severity: ``severity(>= SEVERITY)`` filters out all issues which do not match the severity. Severities: ``CRITICAL``, ``MAJOR``, ``MINOR``, ``INFO``

Filters for other scopes
^^^^^^^^^^^^^^^^^^^^^^^^
These filters can be applied when using all scopes except ``issues``.

:new: Only new elements.
:rating: ``rating(<= RATING)`` filters out all elements which do not match the rating. Ratings: ``A``, ``B``, ``C``, ``D``, ``F``
:metric: ``metric("metric-key", <= 5)`` filters out all elements whose metrics do match the comparison. You can view a list
         of the metric keys by a) clicking on the "raw" button when viewing an element, or b) retrieving data through the `API <https://scrutinizer-ci.com/docs/api>`_.
         Some common metrics are: ``scrutinizer.nb_issues``, ``scrutinizer.test_coverage``, ``scrutinizer.duplicated_lines``

Assertions
~~~~~~~~~~
The last part of the condition is an assertion. Typically you want to use ``exists`` here to check whether one
item matches the filters which you have set-up.

The other use-case is to use ``count`` and compare it against a fixed number, e.g. ``issues.count > 5`` (more than 5 issues).