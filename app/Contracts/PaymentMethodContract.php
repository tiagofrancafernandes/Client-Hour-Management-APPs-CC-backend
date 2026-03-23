<?php

namespace App\Contracts;

use Carbon\Carbon;
use Illuminate\Foundation\Auth\User as Authenticatable;

interface PaymentMethodContract
{
    /**
     * Unique string key used to store the method in the DB.
     */
    public function key(): string;

    /**
     * Internal name (defaults to key).
     */
    public function name(): string;

    /**
     * Human-readable label for display.
     */
    public function label(): string;

    /**
     * Whether this method is available for use.
     */
    public function isActive(): bool;

    /**
     * Whether this method requires offline manual steps (e.g. PIX, cash).
     */
    public function isOffline(): bool;

    /**
     * Currency code this method is restricted to, or null for any currency.
     */
    public function currency(): ?string;

    /**
     * Arbitrary value/config associated with this method (e.g. a PIX key, account number).
     */
    public function value(): mixed;

    /**
     * Relative expiration string or integer seconds.
     *
     * Examples:
     *   '+48 hours'  → expires 48 hours from now
     *   '+1 month'   → expires 1 month from now
     *   '+30 seconds'
     *   '7 days'     → uses strtotime/CarbonInterval parsing
     *   3600         → (integer) 3600 seconds from now
     *   null         → no expiration
     */
    public function expiresTime(): string|int|null;

    /**
     * Calculated Carbon expiration timestamp, or null if no expiration.
     */
    public function expiresAt(): ?Carbon;

    /**
     * Whether this method supports discount on the purchase price.
     */
    public function acceptsDiscount(): bool;

    /**
     * Roles that are allowed to use this method.
     * Empty array means all users are allowed.
     */
    public function allowedUsers(): array;

    /**
     * Check whether a specific user is allowed to use this method.
     */
    public function allowedToUser(Authenticatable $user): bool;

    /**
     * Return the method data as an array.
     */
    public function toArray(): array;

    /**
     * Return the method data as a JSON string.
     */
    public function toJson(): string;
}
