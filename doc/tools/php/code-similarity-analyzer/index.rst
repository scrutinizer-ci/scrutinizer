PHP Code Similarity Analyzer
============================

The code similarity analyzer helps you detect duplicated code in your project. It is robust against code modifications
and not only detects exact clones, but also similar code. In contrast to :doc:`/tools/php/copy-paste-detector/index`,
the found code fragments are usually smaller and provide better targets for refactoring.

.. include :: php_sim_configuration.rst

Increasing the Minimum Mass for Performance
-------------------------------------------
Generally, you do not need to change any settings. If you have a very large code-base, you might want to increase the
minimum mass which correlates to the minimum size of detected code fragments. This will help speed up the analysis at
the cost of not detecting smaller clones:

.. code-block :: yaml

    tools:
        php_sim:
            min_mass: 30 # Defaults to 16

