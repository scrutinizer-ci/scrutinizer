Committing composer.lock for Dependency Analysis
================================================

PHP Analyzer uses the ``composer.lock`` file of your project to determine what dependencies your project
has, and which classes and types they define. This information is cached in a persistent store for subsequent runs.

Troubleshooting non-installable Dependencies
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
If your ``composer.lock`` is not part of your repository, PHP Analyzer will install your vendors in order to generate
it. In some cases, like for example if you require a specific extension or a certain PHP which is not available in the
environment where PHP Analyzer runs, the install command can fail.

Unfortunately, the only options in such a case are:

1. to commit the ``composer.lock`` file to your repository (see below for benefits)
2. or to disable PHP Analyzer

Benefits of Committing the ``composer.lock`` file
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
Committing the ``composer.lock`` file is generally a good practice for projects and can also be used for libraries
for several reasons:

- Your project is kept in a known good state
- All developers operate on the same code-base
- Tests do not suddenly fail because of changes in one of its dependencies.

.. tip ::
    Committing the ``composer.lock`` does not tie any user of your library to a specific version.
