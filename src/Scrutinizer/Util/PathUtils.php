<?php

namespace Scrutinizer\Util;

abstract class PathUtils
{
    public static function matches($path, array $patterns)
    {
        foreach ($patterns as $pattern) {
            if (fnmatch($pattern, $path)) {
                return true;
            }
        }

        return false;
    }

    private final function __construct() { }
}