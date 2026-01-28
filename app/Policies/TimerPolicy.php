<?php

namespace App\Policies;

use App\Models\Timer;
use App\Models\User;

class TimerPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('timer.view_any');
    }

    public function view(User $user, ?Timer $timer = null): bool
    {
        if (!$user->can('timer.view')) {
            return false;
        }

        if ($timer && $timer->user_id !== $user->id) {
            return false;
        }

        return true;
    }

    public function create(User $user): bool
    {
        return $user->can('timer.create');
    }

    public function update(User $user, Timer $timer): bool
    {
        if (!$user->can('timer.update')) {
            return false;
        }

        return $timer->user_id === $user->id;
    }

    public function confirm(User $user, Timer $timer): bool
    {
        if (!$user->can('timer.confirm')) {
            return false;
        }

        return $timer->user_id === $user->id;
    }

    public function delete(User $user, Timer $timer): bool
    {
        if (!$user->can('timer.delete')) {
            return false;
        }

        return $timer->user_id === $user->id;
    }
}
