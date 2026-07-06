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

        // Adicionar informação de completude de setup
        $data = $configs->toArray();
        $data['data'] = array_map(function ($item) {
            $config = PaymentMethodConfig::find($item['id']);
            $item['is_setup_complete'] = $config->isSetupComplete();
            return $item;
        }, $data['data']);

        return response()->json($data);
    }

    public function show(Request $request, PaymentMethodConfig $paymentMethodConfig): JsonResponse
    {
        $user = $request->user();

        if ($user === null || ! $user->can('payment_method.view_any')) {
            abort(403);
        }

        $data = $paymentMethodConfig->toArray();
        $data['setup_field_rules'] = $paymentMethodConfig->getSetupFieldRules();
        $data['setup_field_defaults'] = $paymentMethodConfig->getSetupFieldDefaults();
        $data['is_setup_complete'] = $paymentMethodConfig->isSetupComplete();

        return response()->json($data);
    }

    public function update(
        UpdatePaymentMethodConfigRequest $request,
        PaymentMethodConfig $paymentMethodConfig
    ): JsonResponse {
        $validated = $request->validated();

        // Processar instructions como JSON
        if ($request->has('instructions') && is_array($request->get('instructions'))) {
            $instructions = $request->get('instructions');
            $validated['instructions'] = !empty($instructions) ? json_encode($instructions) : null;
        }

        // Processar setup_fields (já validado no request)
        if ($request->has('setup_fields')) {
            $setupFields = $request->get('setup_fields');
            $validated['setup_fields'] = !empty($setupFields) ? $setupFields : null;
        }

        $paymentMethodConfig->update($validated);

        $data = $paymentMethodConfig->toArray();
        $data['setup_field_rules'] = $paymentMethodConfig->getSetupFieldRules();
        $data['is_setup_complete'] = $paymentMethodConfig->isSetupComplete();

        return response()->json($data);
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
