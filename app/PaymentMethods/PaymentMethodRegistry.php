<?php

namespace App\PaymentMethods;

use InvalidArgumentException;

class PaymentMethodRegistry
{
    /** @var array<string, class-string<AbstractPaymentMethod>> */
    private static array $methods = [];

    /**
     * Register a payment method class.
     *
     * @param  class-string<AbstractPaymentMethod>  $class
     */
    public static function register(string $class): void
    {
        if (! is_subclass_of($class, AbstractPaymentMethod::class)) {
            throw new InvalidArgumentException(
                "Class {$class} must extend " . AbstractPaymentMethod::class
            );
        }

        $instance = new $class();

        static::$methods[$instance->key()] = $class;
    }

    /**
     * Return all registered payment method instances.
     *
     * @return AbstractPaymentMethod[]
     */
    public static function all(): array
    {
        return array_map(
            static fn (string $class) => new $class(),
            static::$methods
        );
    }

    /**
     * Return only active payment method instances.
     *
     * @return AbstractPaymentMethod[]
     */
    public static function active(): array
    {
        return array_values(
            array_filter(
                static::all(),
                static fn (AbstractPaymentMethod $method) => $method->isActive()
            )
        );
    }

    /**
     * Find a payment method by its key, or return null.
     */
    public static function find(string $key): ?AbstractPaymentMethod
    {
        if (! isset(static::$methods[$key])) {
            return null;
        }

        $class = static::$methods[$key];

        return new $class();
    }

    /**
     * Find a payment method by its key, or throw if not found.
     */
    public static function fromKey(string $key): AbstractPaymentMethod
    {
        $method = static::find($key);

        if ($method === null) {
            throw new InvalidArgumentException("Payment method '{$key}' is not registered.");
        }

        return $method;
    }

    /**
     * Return all registered keys.
     *
     * @return string[]
     */
    public static function keys(): array
    {
        return array_keys(static::$methods);
    }

    /**
     * Check if a key is registered.
     */
    public static function has(string $key): bool
    {
        return isset(static::$methods[$key]);
    }

    /**
     * Return all registered methods as an array of arrays.
     */
    public static function toArray(): array
    {
        return array_values(
            array_map(
                static fn (AbstractPaymentMethod $method) => $method->toArray(),
                static::all()
            )
        );
    }

    /**
     * Flush all registered methods (useful for testing).
     */
    public static function flush(): void
    {
        static::$methods = [];
    }
}
