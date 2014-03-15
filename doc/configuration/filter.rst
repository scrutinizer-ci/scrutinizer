File Filter
-----------
By default, Scrutinizer will inspect all files in your project directory. This might include files which you installed
(f.e. dependencies). You can fine tune the analyzed files, using the ``filter`` configuration:

.. code-block :: yaml

    filter:
        paths: [dir1/*, dir2/*]
        excluded_paths: [excluded_dir1/*, excluded_dir2/*]

All paths are defined relative to your root folder, and are checked in this order:

1. A file must match at least one path defined in the ``paths`` setting. If the ``paths`` setting is empty, this will be
   treated like if it would contain a single path ``*``; that is it would always match.

2. A file must not match a single path defined in the ``excluded_paths`` setting.

Some tools might provide additional options of filtering. These are mentioned in the respective documentation chapters
for these tools.