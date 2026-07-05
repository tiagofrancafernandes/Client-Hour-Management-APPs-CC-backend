<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdatePaymentMethodConfigRequest;
use App\Models\PaymentMethodConfig;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentMethodConfigController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user === null || ! $user->can('payment_method.view_any')) {
            abort(403);
        }

        $query = PaymentMethodConfig::query();

        $search = $request->input('search');

        if ($search) {
            $query->where(function ($innerQuery) use ($search) {
                $innerQuery
                    ->where('label', 'ilike', "%{$search}%")
                    ->orWhere('payment_method_key', 'ilike', "%{$search}%");
            });
        }

        if ($request->has('active')) {
            $active = $request->boolean('active');

            $query->where('is_active', $active);
        }

        $query
            ->orderBy('display_order', 'asc')
            ->orderBy('label', 'asc');

        $configs = $query->paginate($request->input('per_page', 15));

        return response()->json($configs);
    }

    public function show(Request $request, PaymentMethodConfig $paymentMethodConfig): JsonResponse
    {
        $user = $request->user();

        if ($user === null || ! $user->can('payment_method.view_any')) {
            abort(403);
        }

        return response()->json($paymentMethodConfig);
    }

    public function update(
        UpdatePaymentMethodConfigRequest $request,
        PaymentMethodConfig $paymentMethodConfig
    ): JsonResponse {
        $validated = $request->validated();

        if ($request->has('instructions') && is_array($request->get('instructions'))) {
            $instructions = $request->get('instructions');
            $validated['instructions'] = !empty($instructions) ? json_encode($instructions) : null;
        }

        $paymentMethodConfig->update($validated);

        return response()->json($paymentMethodConfig);
    }

    public function toggle(Request $request, PaymentMethodConfig $paymentMethodConfig): JsonResponse
    {
        $user = $request->user();

        if ($user === null || ! $user->can('payment_method.toggle')) {
            abort(403);
        }

        $paymentMethodConfig->update([
            'is_active' => ! $paymentMethodConfig->is_active,
        ]);

        $statusMessage = $paymentMethodConfig->is_active ? 'activated' : 'deactivated';

        return response()->json([
            'message' => "Payment method {$statusMessage}",
            'payment_method_config' => $paymentMethodConfig,
        ]);
    }
}
