<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateWalletRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('wallet.update');
    }

    public function rules(): array
    {
        // Get current value or the one being sent
        $wallet = $this->route('wallet');
        $creditPurchaseEnabled = (bool) ($this->input('credit_purchase_allowed') ?? $wallet->credit_purchase_allowed);

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
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
