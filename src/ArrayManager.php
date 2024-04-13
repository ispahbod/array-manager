<?php

namespace Ispahbod\ArrayManager;

class ArrayManager
{
    public static function isAccessible($value): bool
    {
        return is_array($value) || $value instanceof \ArrayAccess;
    }

    public static function appendValue(array $array, $key, $value): void
    {
        if (!isset($array[$key])) {
            $array[$key] = $value;
        }
    }

    public static function mergeArrays(...$arrays): array
    {
        return array_merge([], ...array_filter($arrays, 'is_array'));
    }

    public static function removeKeys(array $array, $keys): array
    {
        static::forgetKey($array, $keys);
        return $array;
    }

    public static function keyExists(array $array, $key): bool
    {
        if (is_float($key)) {
            $key = (string)$key;
        }

        return array_key_exists($key, $array);
    }

    public static function findFirst(array $array, callable $callback = null, $default = null): mixed
    {
        if (empty($array)) {
            return value($default);
        }

        if (is_null($callback)) {
            return reset($array);
        }

        foreach ($array as $key => $value) {
            if ($callback($value, $key)) {
                return $value;
            }
        }

        return value($default);
    }

    public static function findLast(array $array, callable $callback = null, $default = null): mixed
    {
        if (is_null($callback)) {
            return empty($array) ? value($default) : end($array);
        }

        return static::findFirst(array_reverse($array, true), $callback, $default);
    }

    public static function sliceArray(array $array, int $limit): array
    {
        if ($limit < 0) {
            return array_slice($array, $limit, abs($limit));
        }

        return array_slice($array, 0, $limit);
    }

    public static function flattenArray($array, $depth = INF): array
    {
        $result = [];

        foreach ($array as $item) {
            if (!is_array($item)) {
                $result[] = $item;
            } else {
                $values = $depth === 1
                    ? array_values($item)
                    : static::flattenArray($item, $depth - 1);
                foreach ($values as $value) {
                    $result[] = $value;
                }
            }
        }

        return $result;
    }

    public static function forgetKey(array &$array, $keys): void
    {
        $original = &$array;

        $keys = (array)$keys;

        if (count($keys) === 0) {
            return;
        }

        foreach ($keys as $key) {
            if (static::keyExists($array, $key)) {
                unset($array[$key]);

                continue;
            }
            $parts = explode('.', $key);
            $array = &$original;
            while (count($parts) > 1) {
                $part = array_shift($parts);
                if (isset($array[$part]) && static::isAccessible($array[$part])) {
                    $array = &$array[$part];
                } else {
                    continue 2;
                }
            }
            unset($array[array_shift($parts)]);
        }
    }

    public static function getValue(array $array, $key, $default = null): array
    {
        if (!static::isAccessible($array)) {
            return value($default);
        }

        if (is_null($key)) {
            return $array;
        }

        if (static::keyExists($array, $key)) {
            return $array[$key];
        }

        if (!str_contains($key, '.')) {
            return $array[$key] ?? value($default);
        }

        foreach (explode('.', $key) as $segment) {
            if (static::isAccessible($array) && static::keyExists($array, $segment)) {
                $array = $array[$segment];
            } else {
                return value($default);
            }
        }

        return $array;
    }

    public static function containsKeys(array $array, $keys): bool
    {
        $keys = (array)$keys;

        if (!$array || $keys === []) {
            return false;
        }

        foreach ($keys as $key) {
            $subKeyArray = $array;

            if (static::keyExists($array, $key)) {
                continue;
            }

            foreach (explode('.', $key) as $segment) {
                if (static::isAccessible($subKeyArray) && static::keyExists($subKeyArray, $segment)) {
                    $subKeyArray = $subKeyArray[$segment];
                } else {
                    return false;
                }
            }
        }

        return true;
    }

    public static function containsAnyKey(array $array, $keys): bool
    {
        if (is_null($keys)) {
            return false;
        }

        $keys = (array)$keys;

        if (!$array) {
            return false;
        }

        if ($keys === []) {
            return false;
        }

        foreach ($keys as $key) {
            if (static::containsKeys($array, $key)) {
                return true;
            }
        }

        return false;
    }

    public static function isAssociative(array $array): bool
    {
        return !array_is_list($array);
    }

    public static function isSequential(array $array): bool
    {
        return array_is_list($array);
    }

    public static function filterKeys(array $array, $keys): array
    {
        return array_intersect_key($array, array_flip((array)$keys));
    }

    public static function selectKeys(array $array, $keys): array
    {
        $keys = static::wrap($keys);
        return static::mapArray($array, function ($item) use ($keys) {
            $result = [];

            foreach ($keys as $key) {
                if (static::isAccessible($item) && static::keyExists($item, $key)) {
                    $result[$key] = $item[$key];
                } elseif (is_object($item) && isset($item->{$key})) {
                    $result[$key] = $item->{$key};
                }
            }

            return $result;
        });
    }

    public static function mapArray(array $array, callable $callback): array
    {
        $keys = array_keys($array);

        try {
            $items = array_map($callback, $array, $keys);
        } catch (\ArgumentCountError) {
            $items = array_map($callback, $array);
        }

        return array_combine($keys, $items);
    }

    public static function mapArrayWithKeys(array $array, callable $callback): array
    {
        $result = [];

        foreach ($array as $key => $value) {
            $assoc = $callback($value, $key);
            foreach ($assoc as $mapKey => $mapValue) {
                $result[$mapKey] = $mapValue;
            }
        }
        return $result;
    }

    public static function buildQuery(array $array): string
    {
        return http_build_query($array, '', '&', PHP_QUERY_RFC3986);
    }

    public static function extractValue(&$array, $key, $default = null): array
    {
        $value = static::getValue($array, $key, $default);

        static::forgetKey($array, $key);

        return $value;
    }

    public static function assignValue(&$array, $key, $value)
    {
        if (is_null($key)) {
            return $array = $value;
        }

        $keys = explode('.', $key);

        foreach ($keys as $i => $key) {
            if (count($keys) === 1) {
                break;
            }
            unset($keys[$i]);
            if (!isset($array[$key]) || !is_array($array[$key])) {
                $array[$key] = [];
            }

            $array = &$array[$key];
        }

        $array[array_shift($keys)] = $value;

        return $array;
    }

    public static function randomizeArray(array $array, $seed = null):array
    {
        if (is_null($seed)) {
            shuffle($array);
        } else {
            mt_srand($seed);
            shuffle($array);
            mt_srand();
        }

        return $array;
    }


    public static function sortArrayRecursively($array, $options = SORT_REGULAR, $descending = false):array
    {
        foreach ($array as &$value) {
            if (is_array($value)) {
                $value = static::sortArrayRecursively($value, $options, $descending);
            }
        }

        if (!array_is_list($array)) {
            $descending
                ? krsort($array, $options)
                : ksort($array, $options);
        } else {
            $descending
                ? rsort($array, $options)
                : sort($array, $options);
        }

        return $array;
    }

    public static function sortArrayRecursivelyDesc($array, $options = SORT_REGULAR):array
    {
        return static::sortArrayRecursively($array, $options, true);
    }

    public static function filterArray($array, callable $callback):array
    {
        return array_filter($array, $callback, ARRAY_FILTER_USE_BOTH);
    }

    public static function filterNotNull($array):array
    {
        return static::filterArray($array, fn($value) => !is_null($value));
    }

    public static function encapsulate($value):array
    {
        if (is_null($value)) {
            return [];
        }

        return is_array($value) ? $value : [$value];
    }

    public static function convertKeysToLower($array): array
    {
        return array_change_key_case($array, CASE_LOWER);
    }

    public static function convertKeysToUpper($array): array
    {
        return array_change_key_case($array, CASE_UPPER);
    }
    public static function extractRandomElements($array, $quantity = 1, $preserveKeys = false)
    {
        $totalAvailable = count($array);

        if ($quantity > $totalAvailable) {
            $quantity = $totalAvailable;
        }

        if ($quantity <= 0) {
            return [];
        }

        $keys = array_rand($array, $quantity);
        $selectedItems = [];

        foreach ((array)$keys as $key) {
            if ($preserveKeys) {
                $selectedItems[$key] = $array[$key];
            } else {
                $selectedItems[] = $array[$key];
            }
        }

        return $selectedItems;
    }
}