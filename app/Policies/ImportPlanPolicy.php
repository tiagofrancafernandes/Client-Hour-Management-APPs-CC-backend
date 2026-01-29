<?php

namespace App\Policies;

use App\Models\ImportPlan;
use App\Models\User;

class ImportPlanPolicy
{
    /**
     * Determine if user can view any import plans
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('import.view_any');
    }

    /**
     * Determine if user can view the import plan
     */
    public function view(User $user, ImportPlan $importPlan): bool
    {
        if (!$user->hasPermissionTo('import.view')) {
            return false;
        }

        return $importPlan->user_id === $user->id;
    }

    /**
     * Determine if user can create import plans
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('import.create');
    }

    /**
     * Determine if user can update the import plan
     */
    public function update(User $user, ImportPlan $importPlan): bool
    {
        if (!$user->hasPermissionTo('import.update')) {
            return false;
        }

        if ($importPlan->status === 'confirmed') {
            return false;
        }

        return $importPlan->user_id === $user->id;
    }

    /**
     * Determine if user can confirm the import plan
     */
    public function confirm(User $user, ImportPlan $importPlan): bool
    {
        if (!$user->hasPermissionTo('import.confirm')) {
            return false;
        }

        if ($importPlan->status === 'confirmed' || $importPlan->status === 'cancelled') {
            return false;
        }

        return $importPlan->user_id === $user->id;
    }

    /**
     * Determine if user can cancel the import plan
     */
    public function cancel(User $user, ImportPlan $importPlan): bool
    {
        if (!$user->hasPermissionTo('import.cancel')) {
            return false;
        }

        if ($importPlan->status === 'confirmed') {
            return false;
        }

        return $importPlan->user_id === $user->id;
    }

    /**
     * Determine if user can delete the import plan
     */
    public function delete(User $user, ImportPlan $importPlan): bool
    {
        if (!$user->hasPermissionTo('import.delete')) {
            return false;
        }

        if ($importPlan->status === 'confirmed') {
            return false;
        }

        return $importPlan->user_id === $user->id;
    }
}
