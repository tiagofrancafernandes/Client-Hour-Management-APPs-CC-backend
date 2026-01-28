<?php

namespace App\Services;

use App\Models\Timer;
use App\Models\TimerCycle;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Support\Facades\DB;

class TimerService
{
    public function __construct(
        private LedgerService $ledgerService
    ) {
    }

    public function start(User $user, Wallet $wallet, array $data): Timer
    {
        $activeTimer = $this->getActiveTimer($user);

        if ($activeTimer) {
            throw new \Exception('User already has an active timer. Please stop or cancel it first.');
        }

        return DB::transaction(function () use ($user, $wallet, $data) {
            $timer = Timer::create([
                'user_id' => $user->id,
                'wallet_id' => $wallet->id,
                'title' => $data['title'] ?? null,
                'description' => $data['description'] ?? null,
                'status' => 'running',
            ]);

            TimerCycle::create([
                'timer_id' => $timer->id,
                'started_at' => now(),
            ]);

            if (!empty($data['tags'])) {
                $tagIds = is_array($data['tags']) ? $data['tags'] : [$data['tags']];

                $timer->tags()->sync($tagIds);
            }

            return $timer->load(['cycles', 'wallet.client', 'tags']);
        });
    }

    public function pause(Timer $timer): Timer
    {
        if ($timer->status !== 'running') {
            throw new \Exception('Timer must be running to pause it.');
        }

        return DB::transaction(function () use ($timer) {
            $currentCycle = $timer->cycles()->whereNull('ended_at')->first();

            if ($currentCycle) {
                $currentCycle->update(['ended_at' => now()]);
            }

            $timer->update(['status' => 'paused']);

            return $timer->fresh(['cycles', 'wallet.client', 'tags']);
        });
    }

    public function resume(Timer $timer): Timer
    {
        if ($timer->status !== 'paused') {
            throw new \Exception('Timer must be paused to resume it.');
        }

        return DB::transaction(function () use ($timer) {
            TimerCycle::create([
                'timer_id' => $timer->id,
                'started_at' => now(),
            ]);

            $timer->update(['status' => 'running']);

            return $timer->fresh(['cycles', 'wallet.client', 'tags']);
        });
    }

    public function stop(Timer $timer): Timer
    {
        if (!in_array($timer->status, ['running', 'paused'])) {
            throw new \Exception('Timer must be running or paused to stop it.');
        }

        return DB::transaction(function () use ($timer) {
            $currentCycle = $timer->cycles()->whereNull('ended_at')->first();

            if ($currentCycle) {
                $currentCycle->update(['ended_at' => now()]);
            }

            $timer->update(['status' => 'stopped']);

            return $timer->fresh(['cycles', 'wallet.client', 'tags']);
        });
    }

    public function cancel(Timer $timer): Timer
    {
        if ($timer->status === 'confirmed') {
            throw new \Exception('Cannot cancel a confirmed timer.');
        }

        return DB::transaction(function () use ($timer) {
            $currentCycle = $timer->cycles()->whereNull('ended_at')->first();

            if ($currentCycle) {
                $currentCycle->update(['ended_at' => now()]);
            }

            $timer->update(['status' => 'cancelled']);

            return $timer->fresh(['cycles', 'wallet.client', 'tags']);
        });
    }

    public function confirm(Timer $timer, ?array $adjustedCycles = null): Timer
    {
        if ($timer->status !== 'stopped') {
            throw new \Exception('Timer must be stopped to confirm it.');
        }

        foreach ($timer->cycles as $cycle) {
            if (!$cycle->ended_at) {
                throw new \Exception('All cycles must have an end time before confirming.');
            }
        }

        return DB::transaction(function () use ($timer, $adjustedCycles) {
            if ($adjustedCycles) {
                $this->updateCycles($timer, $adjustedCycles);

                $timer->refresh();
            }

            $ledgerEntryData = [
                'wallet_id' => $timer->wallet_id,
                'type' => 'debit',
                'hours' => $timer->total_hours,
                'title' => $timer->title,
                'description' => $timer->description,
                'reference_date' => now()->format('Y-m-d'),
                'tags' => $timer->tags->pluck('id')->toArray(),
            ];

            $ledgerEntry = $this->ledgerService->addDebit(
                $timer->wallet,
                $ledgerEntryData
            );

            $timer->update([
                'status' => 'confirmed',
                'confirmed_at' => now(),
                'ledger_entry_id' => $ledgerEntry->id,
            ]);

            return $timer->fresh(['cycles', 'wallet.client', 'tags', 'ledgerEntry']);
        });
    }

    public function updateCycles(Timer $timer, array $cycles): Timer
    {
        if ($timer->status !== 'stopped') {
            throw new \Exception('Can only update cycles when timer is stopped.');
        }

        return DB::transaction(function () use ($timer, $cycles) {
            $existingCycleIds = $timer->cycles->pluck('id')->toArray();
            $updatedCycleIds = [];

            foreach ($cycles as $cycleData) {
                if (isset($cycleData['id']) && in_array($cycleData['id'], $existingCycleIds)) {
                    $cycle = TimerCycle::find($cycleData['id']);

                    $cycle->update([
                        'started_at' => $cycleData['started_at'],
                        'ended_at' => $cycleData['ended_at'] ?? null,
                    ]);

                    $updatedCycleIds[] = $cycle->id;
                } else {
                    $newCycle = TimerCycle::create([
                        'timer_id' => $timer->id,
                        'started_at' => $cycleData['started_at'],
                        'ended_at' => $cycleData['ended_at'] ?? null,
                    ]);

                    $updatedCycleIds[] = $newCycle->id;
                }
            }

            $cyclesToDelete = array_diff($existingCycleIds, $updatedCycleIds);

            if (!empty($cyclesToDelete)) {
                TimerCycle::whereIn('id', $cyclesToDelete)->delete();
            }

            return $timer->fresh(['cycles', 'wallet.client', 'tags']);
        });
    }

    public function getActiveTimer(User $user): ?Timer
    {
        return Timer::where('user_id', $user->id)
            ->whereIn('status', ['running', 'paused'])
            ->with(['cycles', 'wallet.client', 'tags'])
            ->first();
    }

    public function getUserTimers(User $user, ?string $status = null)
    {
        $query = Timer::where('user_id', $user->id)
            ->with(['cycles', 'wallet.client', 'tags', 'ledgerEntry']);

        if ($status) {
            $query->where('status', $status);
        }

        return $query->orderBy('created_at', 'desc');
    }
}
