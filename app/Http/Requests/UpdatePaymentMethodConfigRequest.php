<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

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
            'display_order' => 'sometimes|required|integer|min:0',
            'setup_fields' => 'sometimes|nullable|array',
            'instructions' => 'sometimes|nullable|array',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator) {
            if ($this->has('setup_fields')) {
                $paymentConfig = $this->route('paymentMethodConfig');

                $setupData = $this->input('setup_fields', []);
                $setupValidator = app(\App\Services\PaymentMethodSetupValidator::class);
                $validation = $setupValidator->validate($paymentConfig, $setupData);

                if (! $validation['valid']) {
                    foreach ($validation['errors'] as $field => $messages) {
                        foreach ($messages as $message) {
                            $validator->errors()->add("setup_fields.{$field}", $message);
                        }
                    }
                }
            }
        });
    }

    protected function passedValidation(): void
    {
        if ($this->has('setup_fields') && is_array($this->get('setup_fields'))) {
            $setupFields = $this->get('setup_fields');
            $this->merge(['setup_fields' => !empty($setupFields) ? $setupFields : null]);
        }

        if ($this->has('instructions') && is_array($this->get('instructions'))) {
            $instructions = $this->get('instructions');
            $this->merge(['instructions' => !empty($instructions) ? $instructions : null]);
        }
    }

    public function messages(): array
    {
        return [
            'label.required' => 'Label do método de pagamento é obrigatório',
            'display_order.integer' => 'Ordem de exibição deve ser um número',
        ];
    }
}
