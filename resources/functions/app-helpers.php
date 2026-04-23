<?php

declare(strict_types=1);

use Carbon\Carbon;
use Illuminate\Support\Collection;

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

if (!function_exists('getTimezoneList)')) {
    /**
     * @param ?callable $filter
     *
     * @return ?Collection
     */
    function getTimezoneList(?callable $filter = null): ?Collection
    {
        $keyOnApp = 'date.timezone_list';

        /** @var ?Collection $collection */
        $collection = value(function () use ($keyOnApp): ?Collection {
            if (!app()->has($keyOnApp)) {
                return null;
            }

            $value = app()->get($keyOnApp);

            return is_a($value, Collection::class) ? $value : null;
        });

        if ($collection) {
            return $filter ? $collection->filter($filter) : $collection;
        }

        $collection = collect(DateTimeZone::listAbbreviations())
            ->map(fn ($item, $key): array => array_map(
                fn ($i) => array_merge([
                    'group' => $key,
                    'tz_group' => $key,
                ], (array) $i),
                $item
            ))
            ->flatten(1)
            ->filter(fn ($i) => $i['timezone_id'] ?? null);

        app()->bind($keyOnApp, fn () => $collection);

        return $filter ? $collection->filter($filter) : $collection;
    }
}

if (!function_exists('getTimezoneFromUtcOffset)')) {
    /**
     * @param mixed $utcOffset
     *
     * @return ?string
     */
    function getTimezoneFromUtcOffset($utcOffset): ?string
    {
        $utcOffset = is_numeric($utcOffset) || is_string($utcOffset) ? $utcOffset : null;

        if (is_null($utcOffset)) {
            return null;
        }

        $padOffset = function (mixed $v): ?string {
            $v = is_string($v) || is_numeric($v) ? $v : null;

            if (is_null($v)) {
                return null;
            }

            $isPositive = $v >= 0;
            $v = preg_replace('/\D+/', '', (string) $v) ?: 0;
            $vLen = strlen((string) $v);

            if ($vLen > 4) {
                $v = intval($v / 36.0);
            }

            $v = str_pad(strval($v), 3, '0', 1);
            $v = substr($v, 0, 2) . substr($v, 2, 2);
            $v = str_pad(strval($v), 4, '0', 0);

            return $isPositive ? "+{$v}" : "-{$v}";
        };

        if (is_numeric($utcOffset)) {
            $utcOffset = $padOffset($utcOffset);
        }

        preg_match('/^[\+|\-]{0,1}[\d]{1,2}([\:]{0,1}[\d]{1,2}){0,1}$/i', $utcOffset, $matchOffset);
        $matchOffset = strval($matchOffset[0] ?? null);

        if ($matchOffset) {
            $matchOffset = $matchOffset ? intval(str_replace([':', '.'], '', $matchOffset)) : null;
            $utcOffset = $matchOffset ?? null;
        }

        if (is_null($utcOffset) || !is_numeric($utcOffset)) {
            return null;
        }

        $utcOffsetLen = strlen((string) intval(preg_replace('/\D+/', '', (string) $utcOffset)));
        $utcOffset = $utcOffsetLen ? intval($utcOffsetLen <= 4 ? $padOffset($utcOffset) * 36.0 : $utcOffset) : null;

        if (is_null($utcOffset)) {
            return null;
        }

        if (in_array($utcOffset, [0, /* -1, -60 */])) {
            return 'UTC';
        }

        $defaultTzByUtcOffset = [
            0 => 'UTC',
            -10800 => 'America/Sao_Paulo'
        ];

        if (array_key_exists($utcOffset, $defaultTzByUtcOffset)) {
            return $defaultTzByUtcOffset[$utcOffset] ?? null;
        }

        $result = getTimezoneList()->first(fn ($i) => ($i['offset'] ?? 0) === $utcOffset);

        if (!$result) {
            return null;
        }

        return $result['timezone_id'] ?? null;
    }
}

if (!function_exists('getTimezoneName)')) {
    /**
     * @param mixed $value
     * @param ?string $default
     *
     * @return ?string
     */
    function getTimezoneName($value = null, ?int $utcOffset = null, ?string $default = 'UTC'): ?string
    {
        if (is_numeric($value) && $tz = getTimezoneFromUtcOffset($value)) {
            return $tz;
        }

        if (!is_null($utcOffset) && strlen((string) $utcOffset) <= 6) {
            $value = 'UNKNOWN';
        }

        $value = is_string($value) && trim($value) ? trim($value) : null;

        if ($value) {
            preg_match('/^[\+|\-]{0,1}[\d]{1,2}([\:]{0,1}[\d]{1,2}){0,1}$/i', $value, $matchOffset);
            $matchOffset = strval($matchOffset[0] ?? null);
            $matchOffset = $matchOffset ? intval(str_replace([':', '.'], '', $matchOffset)) : null;
        }

        if (isset($matchOffset) && $tz = getTimezoneFromUtcOffset(intval($matchOffset) * 36.0)) {
            return $tz;
        }

        if ($matchOffset ?? null) {
            $utcOffset = intval($matchOffset) * 36.0;
            $value = 'UNKNOWN';
        }

        $utcOffset = match (strtoupper($value ?: 'UTC')) {
            'BRT' => -10800,
            'UTC' => 0,
            default => is_numeric($utcOffset ?? null) ? $utcOffset : 0,
        };

        $utcOffsetLen = strlen((string) intval(preg_replace('/\D+/', '', (string) $utcOffset)));

        $utcOffset = $utcOffsetLen <= 3 ? $utcOffset * 36.0 : $utcOffset;

        $getTz = function (?string $v) use ($utcOffset) {
            if (!$v) {
                return null;
            }

            if (mb_strripos($v ?: '', '/') !== false) {
                $_v = array_find(timezone_identifiers_list(), fn ($i) => mb_strripos($i ?: '', $v) === 0);
            }

            if ($_v ?? null) {
                return $_v;
            }

            return timezone_name_from_abbr($v, (int) $utcOffset, 0) ?: null;
        };

        if ($tz = $getTz($value)) {
            return $tz;
        }

        return $getTz($default) ?: 'UTC';
    }
}

if (!function_exists('getTimezoneFromAbbreviation)')) {
    /**
     * @param mixed $value
     * @param ?string $default
     *
     * @return ?string
     */
    function getTimezoneFromAbbreviation($value = null, ?int $utcOffset = null, ?string $default = 'UTC'): ?string
    {
        return getTimezoneName(
            value: $value,
            utcOffset: $utcOffset,
            default: $default,
        );
    }
}

if (!function_exists('hasTimezoneInString')) {
    /**
     * @param mixed $value
     *
     * @return bool
     */

    function hasTimezoneInString(mixed $value): bool
    {
        if (!is_string($value)) {
            return false;
        }

        /* Ex: '2026-04-22T19:41:33.000000Z' */
        if (preg_match('/^([0-9]){4}\-[0-9]{2}\-[0-9]{2}[T][0-9]{2}\:[0-9]{2}\:[0-9]{2}\.(.*)/', $value)) {
            return true;
        }

        /* Ex: '2026-04-22T16:46:21-03:00' */
        if (preg_match('/^([0-9]){4}\-[0-9]{2}\-[0-9]{2}[T][0-9]{2}\:[0-9]{2}\:[0-9]{2}[\+|\-][0-9]{2}(\:[0-9]{2}){0,1}(.*)/', $value)) {
            return true;
        }

        // ISO 8601 timezone detection (Z ou offset)
        if (preg_match('/(Z|[+\-]\d{2}:\d{2})$/', $value)) {
            return true;
        }

        return false;
    }
}

if (!function_exists('resolveReferenceDateAndTimezone')) {
    /**
     * @param DateTimeInterface|\Carbon\CarbonInterface|string|null $referenceDate
     * @param DateTimeZone|string|int|null $referenceDateTimezone
     *
     * @return array
     */
    function resolveReferenceDateAndTimezone(
        DateTimeInterface|\Carbon\CarbonInterface|string|null $referenceDate = null,
        DateTimeZone|string|int|null $referenceDateTimezone = null,
        null|string $dateFormat = null,
    ): array {
        try {
            // Validate
            if (!$referenceDate) {
                return [
                    'reference_date' => null,
                    'reference_date_timezone' => null,
                ];
            }

            // Prepare
            $hasExplicitTimezone = hasTimezoneInString($referenceDate);

            // Execute - prioridade: timezone informado externamente
            if ($referenceDateTimezone) {
                $date = Carbon::parse($referenceDate, $referenceDateTimezone);

                return [
                    'reference_date' => $dateFormat ? $date->format($dateFormat) : $date->toDateString(),
                    'reference_date_timezone' => $referenceDateTimezone,
                ];
            }

            // Execute - extrair da string
            if ($hasExplicitTimezone) {
                $date = new Carbon($referenceDate);

                return [
                    'reference_date' => $dateFormat ? $date->format($dateFormat) : $date->toDateString(),
                    'reference_date_timezone' => $date->getTimezone()->getName(),
                ];
            }

            // Fallback - assumir UTC
            $date = Carbon::parse($referenceDate, 'UTC');

            return [
                'reference_date' => $dateFormat ? $date->format($dateFormat) : $date->toDateString(),
                'reference_date_timezone' => 'UTC',
            ];
        } catch (Throwable $th) {
            //throw $th;
            return [
                'reference_date' => null,
                'reference_date_timezone' => null,
            ];
        }
    }
}

if (!function_exists('tryParseDate')) {
    /**
     * @param mixed $value
     * @param bool|null $debug
     *
     * @return DateTime|null
     */
    function tryParseDate($value, ?bool $debug = null): ?DateTime
    {
        try {
            if (!$value || !(is_string($value) && trim($value))) {
                return null;
            }

            if (is_object($value) && is_a($value, DateTime::class)) {
                return $value;
            }

            if (is_object($value) && is_a($value, Carbon::class)) {
                return new DateTime($value->toString());
            }

            if (is_string($value) && !is_numeric($value)) {
                return new DateTime(now()->parse($value)->toString());
            }

            if (is_numeric($value) && strlen((string) $value) > 9) {
                return new DateTime(now()->parse((int) $value)->toString());
            }

            return new DateTime(now()->parse($value)->toString());
        } catch (Throwable $th) {
            if ($debug) {
                throw $th;
            }

            return null;
        }
    }
}

if (!function_exists('makeDateTimeZone')) {
    /**
     * function makeDateTimeZone
     *
     * @param DateTimeZone|DateTime|string|int|null $from
     * @param bool|null $onlyName
     *
     * @return DateTimeZone|string|null
     */
    function makeDateTimeZone(
        DateTimeZone|DateTime|string|int|null $from = null,
        bool|null $onlyName = null,
    ): DateTimeZone|string|null {
        try {
            if (is_string($from)) {
                try {
                    $from = new DateTimezone($from);
                } catch (Throwable) {
                    $from = strlen(trim($from)) ? trim($from) : null;
                }
            }

            if (is_numeric($from) && $from == 0) {
                $from = new DateTimeZone('UTC');
            }

            $makeReturn = fn (?DateTimeZone $v) => $v && $onlyName ? getTimezoneName($v?->getName()) : $v;

            if (is_object($from) && is_a($from, DateTimeZone::class)) {
                return $makeReturn($from);
            }

            if (is_object($from) && is_a($from, DateTime::class)) {
                return $makeReturn($from->getTimezone());
            }

            $resolved = null;

            if (!$resolved && is_string($from) && !is_numeric($from)) {
                $resolved = tryParseDate($from);
            }

            if ($resolved && is_object($resolved) && is_a($resolved, DateTime::class)) {
                return $makeReturn($resolved->getTimezone());
            }

            if (!$resolved && is_string($from) && !is_numeric($from)) {
                $resolved = getTimezoneName($from);
            }

            if (!$resolved && is_numeric($from) && strlen((string) $from) > 9) {
                $resolved = getTimezoneFromUtcOffset(now()->parse((int) $from)->getTimezone()->getName());
            }

            if (!$resolved && is_numeric($from)) {
                $resolved = getTimezoneFromUtcOffset($from);
            }

            if ($resolved) {
                return $makeReturn(new DateTimeZone($resolved));
            }

            $resolved = getTimezoneName($from);

            return $makeReturn($resolved ? new DateTimeZone($resolved) : new DateTimeZone('UTC'));
        } catch (Throwable $th) {
            // throw $th;

            return null;
        }
    }
}

if (!function_exists('getTimezoneFrom')) {
    /**
     * function getTimezoneFrom
     *
     * @param DateTimeZone|DateTime|string|int|null $from
     * @param bool|null $onlyName
     *
     * @return DateTimeZone|string|null
     */
    function getTimezoneFrom(
        DateTimeZone|DateTime|string|int|null $from = null,
        bool|null $onlyName = null,
    ): DateTimeZone|string|null {
        return makeDateTimeZone(
            from: $from,
            onlyName: $onlyName,
        );
    }
}
