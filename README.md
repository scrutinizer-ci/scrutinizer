Scrutinizer
===========

Scrutinizer runs various analysis tools on your code, collects,
and displays the results for you. It can also be integrated into your CI set-up.

Installation
------------

After downloading, you need to install the vendors via [composer](https://getcomposer.org):

```
composer install --dev
```

Then, you can use the executable in ``bin/scrutinizer`` or alias it by placing a script in ``/usr/bin/scrutinizer``:

```
#!/bin/sh

/usr/bin/env php /path/to/bin/scrutinizer $*
```

Configuration
-------------

Scrutinizer uses a configuration file named ``.scrutinizer.yml`` which it expects in the root folder of your
project.

To enable a tool, a minimal configuration such as:

```
tools:
    js_hint: ~
```

is sufficient, and would enable [JsHint](http://www.jshint.com/). For a complete reference, see below.


Reference
#########

```
# .scrutinizer.yml

# Allows you to filter which files are included in the review; by default, all files.
filter:               

    # Patterns must match the entire path to apply; "src/" will not match "src/foo".
    paths:                [] # Example: [src/*, tests/*]
    excluded_paths:       [] # Example: [tests/*/Fixture/*]

# Commands which are executed before/after the tools below.
before_commands:      [] 
after_commands:       [] 

tools: 
    # Runs the JSHint static analysis tool.
    js_hint:              

        # Whether to use JSHint's native config file, .jshintrc.
        use_native_config:    true 

        extensions: [js]
        enabled:              false 
        
        filter:               
            paths:                [] 
            excluded_paths:       [] 

        # All options that are supported by JSHint (see http://jshint.com/docs/); only available when "use_native_config" is set to "false".
        config:              {}
         
        path_configs:         
            -                     
                paths:                [] 
                enabled:              true 

                # All options that are supported by JSHint (see http://jshint.com/docs/); only available when "use_native_config" is set to "false".
                config:              {} 


    # Runs the PHP Mess Detector (http://phpmd.org).
    php_md:               
        extensions:           [php] 
        enabled:              false 
        filter:               
            paths:                [] 
            excluded_paths:       [] 
        config:               
            rulesets: [codesize]
            
        path_configs:         

            -                     
                paths:                [] 
                enabled:              true 
                config:               
                    rulesets: [codesize]            
```
