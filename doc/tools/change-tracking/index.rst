Tracking Code Changes/Stability
-------------------------------

Scrutinizer analyzes the stability of the different parts of your code on a per-commit basis.
To better understand what kind of change a commit makes, Scrutinizer introspects the commit
title and labels commits as feature additions, or bug fixes using regular expressions.

You can change the used regular expressions to fit them to your workflow. Below is the default configuration:

.. code-block :: yaml

    # .scrutinizer.yml
    changetracking:
        bug_patterns: ["\bfix(?:es|ed)?\b"]
        feature_patterns: ["\badd(?:s|ed)?\b", "\bimplement(?:s|ed)?\b"]

