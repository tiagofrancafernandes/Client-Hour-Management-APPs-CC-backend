<?php

namespace App\PaymentMethods;

use App\Contracts\PaymentMethodContract;
use Carbon\Carbon;
use Carbon\CarbonInterval;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use Illuminate\Foundation\Auth\User as Authenticatable;

abstract class AbstractPaymentMethod implements PaymentMethodContract, Arrayable, Jsonable
{
    abstract public function key(): string;

    abstract public function label(): string;

    public function name(): string
    {
        return $this->key();
    }

    public function isActive(): bool
    {
        return true;
    }

    public function isOffline(): bool
    {
        return false;
    }

    public function currency(): ?string
    {
        return null;
    }

    public function value(): mixed
    {
        return null;
    }

    public function expiresTime(): string|int|null
    {
        return null;
    }

    public function expiresAt(): ?Carbon
    {
        $time = $this->expiresTime();

        if ($time === null) {
            return null;
        }

        if (is_int($time)) {
            return Carbon::now()->addSeconds($time);
        }

        // Try strtotime-compatible string first (handles '+48 hours', '+1 month', etc.)
        $timestamp = strtotime($time, time());

        if ($timestamp !== false) {
            return Carbon::createFromTimestamp($timestamp);
        }

        // Fallback to CarbonInterval for strings like '48 hours', '7 days'
        try {
            return Carbon::now()->add(CarbonInterval::fromString($time));
        } catch (\Exception) {
            return null;
        }
    }

    public function acceptsDiscount(): bool
    {
        return true;
    }

    public function allowedUsers(): array
    {
        return [];
    }

    public function allowedToUser(Authenticatable $user): bool
    {
        $allowed = $this->allowedUsers();

        if (empty($allowed)) {
            return true;
        }

        if (method_exists($user, 'hasAnyRole')) {
            return $user->hasAnyRole($allowed);
        }

        return true;
    }

    public function toArray(): array
    {
        return [
            'key' => $this->key(),
            'name' => $this->name(),
            'label' => $this->label(),
            'is_active' => $this->isActive(),
            'is_offline' => $this->isOffline(),
            'currency' => $this->currency(),
            'value' => $this->value(),
            'expires_time' => $this->expiresTime(),
            'accepts_discount' => $this->acceptsDiscount(),
            'allowed_users' => $this->allowedUsers(),
        ];
    }

    public function toJson($options = 0): string
    {
        return json_encode($this->toArray(), JSON_THROW_ON_ERROR | $options);
    }
}
