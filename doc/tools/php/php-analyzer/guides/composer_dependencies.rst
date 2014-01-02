Committing composer.lock for Dependency Analysis
================================================

PHP Analyzer uses the ``composer.lock`` file of your project to determine what dependencies your project
has, and which classes and types they define. This information is cached in a persistent store for subsequent runs.

In order for PHP Analyzer to determine the dependencies, you need to commit the ``composer.lock`` file of your project
to your repository. While PHP Analyzer could run ``composer install`` this slows down analysis and causes other
side-effects which are inherent in composer's reliance on the GitHub platform as distribution channel and GitHub's rate
limits. Therefore, this is not supported.

Committing the ``composer.lock`` file is generally a good practice even for library packages for several reasons:

- Your project is kept in a known good state
- All developers operate on the same code-base
- Tests do not suddenly fail because of changes in one of its dependencies.

Committing the ``composer.lock`` file also has no side-effects for any user of your library.