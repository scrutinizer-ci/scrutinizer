<?php

namespace Scrutinizer\Util;

abstract class PathUtils
{
    public static function isFiltered($path, array $filter)
    {
        if ( ! empty($filter['paths']) && ! self::matches($path, $filter['paths'])) {
            return true;
        }

        if ( ! empty($filter['excluded_paths']) && self::matches($path, $filter['excluded_paths'])) {
            return true;
        }

        return false;
    }

    public static function matches($path, array $patterns)
    {
        foreach ($patterns as $pattern) {
            if (fnmatch($pattern, $path)) {
                return true;
            }
        }

        return false;
    }

    final private function __construct() { }
}