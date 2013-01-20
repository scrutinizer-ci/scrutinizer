PHP Code Coverage with Travis CI
================================
If you have Travis CI set-up for your repository, you can display PHP Code Coverage
information for pushes, and pull requests on the review details page.

Setting Up Reviews
------------------
In case, you have not yet set-up reviews for your repository, please see the 
:doc:`Setup Guide </guides/setting_up_reviews>`.

Modifying your .travis.yml
--------------------------
Once, you have set-up reviews, all that is left to do is to modify your .travis.yml to
generate, and upload the PHP code coverage report.

.. code-block :: yml

    ## .travis.yml
    
    # Let phpunit generate the code coverage report in the clover format
    script: phpunit --coverage-clover "clover"
    
    after_success: 
        - curl -sL https://bit.ly/artifact-uploader | php

