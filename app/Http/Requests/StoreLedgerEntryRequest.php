<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreLedgerEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        $type = $this->input('type', 'debit');

        if ($type === 'credit') {
            return $this->user()->can('ledger.credit');
        }

        if ($type === 'adjustment') {
            return $this->user()->can('ledger.adjust');
        }

        return $this->user()->can('ledger.debit');
    }

    public function rules(): array
    {
        return [
            'wallet_id' => ['required', 'integer', 'exists:wallets,id'],
            'type' => ['required', 'string', 'in:credit,debit,adjustment'],
            'hours' => ['required', 'numeric', 'not_in:0'],
            'title' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'reference_date' => ['nullable', 'date'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['integer', 'exists:tags,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'hours.not_in' => 'The hours field must not be zero.',
        ];
    }
}
