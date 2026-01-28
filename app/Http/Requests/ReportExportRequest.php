<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReportExportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('report.view');
    }

    public function rules(): array
    {
        return [
            'format' => ['required', 'string', 'in:pdf,excel'],
            'client_id' => ['nullable', 'integer', 'exists:clients,id'],
            'wallet_id' => ['nullable', 'integer', 'exists:wallets,id'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['integer', 'exists:tags,id'],
            'type' => ['nullable', 'string', 'in:credit,debit'],
        ];
    }
}
