<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreLedgerEntryRequest extends FormRequest
{
    /**
     * Prepare the data for validation.
     *
     * @return void
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            //
        ]);
    }

    /**
     * Get data to be validated from the request.
     *
     * @return array
     */
    public function validationData()
    {
        $data = parent::validationData();
        $referenceDate = $data['reference_date_timezone'] ?? null;
        $tzFromReferenceDate = getTimezoneFrom($referenceDate, onlyName: true);
        $data['changedOn'] ??= '';
        $data['changedOn'] = ($data['changedOn'] ? $data['changedOn'] . ':' : '') . 'validationData';

        $resolvedDate = value(function () use ($data) {
            try {
                return resolveReferenceDateAndTimezone(
                    $data['reference_date'] ?? null,
                    $data['reference_date_timezone'] ?? null,
                );
            } catch (\Throwable $th) {
                return [
                    'reference_date' => $data['reference_date'] ?? null,
                    'reference_date_timezone' => $data['reference_date_timezone'] ?? null,
                ];
            }
        });

        $data['reference_date'] = $resolvedDate['reference_date'] ?? $data['reference_date'] ?? null;
        $data['reference_date_timezone'] = getTimezoneFrom(
            $resolvedDate['reference_date_timezone'] ?? $tzFromReferenceDate,
            onlyName: true,
        );

        return $data;
    }

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
            'reference_date_timezone' => [
                'nullable',
                'string',
                'timezone',
            ],
            'tags' => ['nullable', 'array'],
            'changedOn' => ['nullable'],
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
