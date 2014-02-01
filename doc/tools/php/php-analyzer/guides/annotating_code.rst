Enhancing Type Inference with Doc Comments
==========================================

Introduction
------------
During analysis, we use type information in your code to enhance checks. PHP
offers already offers some support for type-hinting. You can enhance PHP's
built-in type-hints using doc comments.

Supported Doc Comments
----------------------
PHP Analyzer currently reads the following doc comments:

- ``@param [type] $[paramName]`` for functions/methods
- ``@return [type]`` for functions/methods
- ``@var [type] $[variableName]`` everywhere


Recommendations
---------------
Interfaces
~~~~~~~~~~
Since interfaces have no code from which a type can be inferred. Methods should
always be annotated. For parameters, a comment is not strictly necessary if it
is already covered by a type hint. Return types should always be specified via
a comment, even if the method does not have a return value in which case you
can use the ``void`` type.

Arrays
~~~~~~
PHP itself provides the generic ``array`` type hint. Unfortunately, that does
not allow to specify what the types of its values are. Therefore, we encourage
to specify a more specific type via comments using either the ``Type[]`` syntax,
or the ``array<Type>`` syntax; both are equivalent.


Type Reference
--------------
This is a reference of which types are supported in doc comments.

+---------------------------------+-----------------------------------------------+
| Type                            | Description                                   |
+=================================+===============================================+
| ``boolean``, ``Boolean``, or    | Value can be ``true``, or ``false``           |
| ``bool``                        |                                               |
+---------------------------------+-----------------------------------------------+
| ``false``                       | Value can be only boolean ``false``. This only|
|                                 | makes sense in combination with another       |
|                                 | type, e.g. ``false|string``.                  |
+---------------------------------+-----------------------------------------------+
| ``integer``, or ``int``         | Value is an integer.                          |
+---------------------------------+-----------------------------------------------+
| ``float``, or ``double``        | Value is a float.                             |
+---------------------------------+-----------------------------------------------+
| ``string``                      | Value is a string.                            |
+---------------------------------+-----------------------------------------------+
| ``null``                        | Value is null. This only makes sense in       |
|                                 | combination with another type, e.g.           |
|                                 | ``string|null``.                              |
+---------------------------------+-----------------------------------------------+
| ``void``                        | Value is of no type. This type is solely      |
|                                 | reserved for return type of methods that do   |
|                                 | not return any value.                         |
+---------------------------------+-----------------------------------------------+
| ``self``, or ``$this``          | Value is an object. This is reserved for      |
|                                 | cases where you return ``$this`` from a       |
|                                 | method, and is resolved to the class on       |
|                                 | which the method is being called.             |
+---------------------------------+-----------------------------------------------+
| ``array``                       | Value is an array with arbitrary              |
|                                 | keys/values.                                  |
+---------------------------------+-----------------------------------------------+
| ``array<T>``, ``T[]``           | Value is an array with integer keys, and      |
|                                 | elements of type T where T can be any         |
|                                 | available type.                               |
+---------------------------------+-----------------------------------------------+
| ``ClassName``                   | Value is an object of the given class.        |
+---------------------------------+-----------------------------------------------+
| ``A|B``                         | Value is of type ``A``, or type ``B`` where   |
|                                 | ``A``, and ``B`` are any available type.      |
+---------------------------------+-----------------------------------------------+
| ``mixed``, or ``*``             | Value is of any available type. We encourage  |
|                                 | you to avoid this type whenever you can.      |
+---------------------------------+-----------------------------------------------+

If a certain type that you use in your code is not yet supported and you feel that it
is widely used, please open an issue on `scrutinizer-ci/php-analyzer
<https://github.com/scrutinizer-ci/php-analyzer>`_.
