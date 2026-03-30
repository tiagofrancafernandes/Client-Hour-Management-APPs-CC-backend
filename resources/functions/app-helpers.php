<?php

if (! function_exists('ensure_string')) {
    /**
     * Ensure that the given value is returned as an string.
     *
     * If the value is already an string, it will be returned unchanged.
     * Otherwise, the value will be wrapped in a new string.
     *
     * @param mixed $value
     * @param ?bool $allowJsonEncode
     * @param ?int $flags
     *
     * @return string
     */
    function ensure_string($value = '', ?bool $allowJsonEncode = null, ?int $flags = 64): string
    {
        $flags ??= 64;

        if (is_string($value) || is_scalar($value)) {
            return strval($value);
        }

        if (is_object($value) && $allowJsonEncode) {
            return is_a($value, Stringable::class) ? strval($value) : json_encode($value, $flags);
        }

        if (is_object($value) && !$allowJsonEncode) {
            return is_a($value, Stringable::class) ? strval($value) : '';
        }

        if (is_array($value) && $allowJsonEncode) {
            return json_encode($value, $flags);
        }

        return $allowJsonEncode ? json_encode($value, $flags) : '';
    }
}

if (! function_exists('ensure_array')) {
    /**
     * Ensure that the given value is returned as an array.
     *
     * If the value is already an array, it will be returned unchanged.
     * Otherwise, the value will be wrapped in a new array.
     *
     * @param mixed $value
     * @return array
     */
    function ensure_array($value): array
    {
        return is_array($value) ? $value : [$value];
    }
}

if (! function_exists('array_wrap')) {
    /**
     * Wrap the given value in an array.
     *
     * If $forceWrap is false, the function behaves like ensure_array():
     * - Arrays are returned unchanged
     * - Non-arrays are wrapped in an array
     *
     * If $forceWrap is true, the value is always wrapped in a new array,
     * even if it is already an array.
     *
     * Examples:
     * - array_wrap(123) => [123]
     * - array_wrap([123]) => [123]
     * - array_wrap([123], true) => [[123]]
     *
     * @param mixed $value
     * @param bool $forceWrap
     * @return array
     */
    function array_wrap($value, bool $forceWrap = false): array
    {
        if ($forceWrap) {
            return [$value];
        }

        return is_array($value) ? $value : [$value];
    }
}

if (! function_exists('head')) {
    /**
     * Get the first element of an array. Useful for method chaining.
     *
     * @param  array  $array
     * @return mixed
     */
    function head($array)
    {
        $array = (array) $array;

        return empty($array) ? false : array_first($array);
    }
}

if (! function_exists('last')) {
    /**
     * Get the last element from an array.
     *
     * @param  array  $array
     * @return mixed
     */
    function last($array)
    {
        $array = (array) $array;

        return empty($array) ? false : array_last($array);
    }
}

if (! function_exists('value')) {
    /**
     * Return the default value of the given value.
     *
     * @template TValue
     * @template TArgs
     *
     * @param  TValue|Closure(TArgs): TValue  $value
     * @param  TArgs  ...$args
     * @return TValue
     */
    function value($value, ...$args)
    {
        return $value instanceof Closure ? $value(...$args) : $value;
    }
}

if (! function_exists('when')) {
    /**
     * Return a value if the given condition is true.
     *
     * @param  mixed  $condition
     * @param  Closure|mixed  $value
     * @param  Closure|mixed  $default
     * @return mixed
     */
    function when($condition, $value, $default = null)
    {
        $condition = $condition instanceof Closure ? $condition() : $condition;

        if ($condition) {
            return value($value, $condition);
        }

        return value($default, $condition);
    }
}
