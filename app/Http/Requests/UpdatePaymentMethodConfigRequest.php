<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePaymentMethodConfigRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('payment_method.update_any');
    }

    public function rules(): array
    {
        return [
            'label' => 'sometimes|required|string|max:255',
            'instructions' => 'nullable|string',
            'display_order' => 'sometimes|required|integer|min:0',
        ];
    }

    public function messages(): array
    {
        return [
            'label.required' => 'Label do método de pagamento é obrigatório',
            'instructions.string' => 'Instruções devem ser texto',
            'display_order.integer' => 'Ordem de exibição deve ser um número',
        ];
    }
}
