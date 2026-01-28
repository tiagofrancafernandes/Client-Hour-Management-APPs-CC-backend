<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Timer;
use App\Models\Wallet;
use App\Services\TimerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TimerController extends Controller
{
    public function __construct(
        private TimerService $timerService
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Timer::class);

        $status = $request->input('status');

        $timers = $this->timerService
            ->getUserTimers($request->user(), $status)
            ->paginate(15);

        return response()->json($timers);
    }

    public function active(Request $request): JsonResponse
    {
        $this->authorize('view', Timer::class);

        $activeTimer = $this->timerService->getActiveTimer($request->user());

        return response()->json($activeTimer);
    }

    public function show(Timer $timer): JsonResponse
    {
        $this->authorize('view', $timer);

        $timer->load(['cycles', 'wallet.client', 'tags', 'ledgerEntry']);

        return response()->json($timer);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Timer::class);

        $validated = $request->validate([
            'wallet_id' => ['required', 'integer', 'exists:wallets,id'],
            'title' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['integer', 'exists:tags,id'],
        ]);

        $wallet = Wallet::findOrFail($validated['wallet_id']);

        try {
            $timer = $this->timerService->start($request->user(), $wallet, $validated);

            return response()->json($timer, 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function pause(Timer $timer): JsonResponse
    {
        $this->authorize('update', $timer);

        try {
            $timer = $this->timerService->pause($timer);

            return response()->json($timer);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function resume(Timer $timer): JsonResponse
    {
        $this->authorize('update', $timer);

        try {
            $timer = $this->timerService->resume($timer);

            return response()->json($timer);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function stop(Timer $timer): JsonResponse
    {
        $this->authorize('update', $timer);

        try {
            $timer = $this->timerService->stop($timer);

            return response()->json($timer);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function cancel(Timer $timer): JsonResponse
    {
        $this->authorize('update', $timer);

        try {
            $timer = $this->timerService->cancel($timer);

            return response()->json($timer);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function confirm(Request $request, Timer $timer): JsonResponse
    {
        $this->authorize('confirm', $timer);

        $validated = $request->validate([
            'cycles' => ['nullable', 'array'],
            'cycles.*.id' => ['nullable', 'integer', 'exists:timer_cycles,id'],
            'cycles.*.started_at' => ['required', 'date'],
            'cycles.*.ended_at' => ['nullable', 'date', 'after:cycles.*.started_at'],
        ]);

        try {
            $timer = $this->timerService->confirm($timer, $validated['cycles'] ?? null);

            return response()->json($timer);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function update(Request $request, Timer $timer): JsonResponse
    {
        $this->authorize('update', $timer);

        if ($timer->status !== 'stopped') {
            return response()->json([
                'message' => 'Can only update timer when stopped.',
            ], 422);
        }

        $validated = $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['integer', 'exists:tags,id'],
        ]);

        $timer->update([
            'title' => $validated['title'] ?? $timer->title,
            'description' => $validated['description'] ?? $timer->description,
        ]);

        if (isset($validated['tags'])) {
            $timer->tags()->sync($validated['tags']);
        }

        return response()->json($timer->fresh(['cycles', 'wallet.client', 'tags']));
    }

    public function updateCycles(Request $request, Timer $timer): JsonResponse
    {
        $this->authorize('update', $timer);

        $validated = $request->validate([
            'cycles' => ['required', 'array'],
            'cycles.*.id' => ['nullable', 'integer', 'exists:timer_cycles,id'],
            'cycles.*.started_at' => ['required', 'date'],
            'cycles.*.ended_at' => ['nullable', 'date', 'after:cycles.*.started_at'],
        ]);

        try {
            $timer = $this->timerService->updateCycles($timer, $validated['cycles']);

            return response()->json($timer);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function destroy(Timer $timer): JsonResponse
    {
        $this->authorize('delete', $timer);

        if (!in_array($timer->status, ['confirmed', 'cancelled'])) {
            return response()->json([
                'message' => 'Can only delete confirmed or cancelled timers.',
            ], 422);
        }

        $timer->delete();

        return response()->json(null, 204);
    }
}
