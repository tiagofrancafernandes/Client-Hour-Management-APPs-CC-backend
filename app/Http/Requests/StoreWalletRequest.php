<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreWalletRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('wallet.create');
    }

    public function rules(): array
    {
        $creditPurchaseEnabled = (bool) $this->input('credit_purchase_allowed');

        return [
            'client_id' => ['required', 'integer', 'exists:clients,id'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'hourly_rate_reference' => [
                $creditPurchaseEnabled ? 'required' : 'nullable',
                'numeric',
                'min:0',
            ],
            'currency_code' => [
                $creditPurchaseEnabled ? 'required' : 'nullable',
                'string',
                'size:3',
                'uppercase',
            ],
            'credit_purchase_allowed' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'hourly_rate_reference.required' => 'Hourly rate is required when credit purchase is enabled',
            'currency_code.required' => 'Currency is required when credit purchase is enabled',
        ];
    }
}
