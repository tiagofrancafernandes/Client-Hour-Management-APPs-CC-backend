<?php

namespace App\Services;

use App\Models\PaymentMethodConfig;

class PaymentMethodSetupValidator
{
    public function validate(PaymentMethodConfig $config, array $setupData): array
    {
        $rules = $config->getSetupFieldRules();
        $errors = [];

        foreach ($rules as $rule) {
            $fieldName = $rule['name'];
            $value = $setupData[$fieldName] ?? null;

            // Validate required fields
            $isRequired = $rule['required'] ?? false;
            if ($isRequired && empty($value)) {
                $errors[$fieldName][] = "Campo obrigatório";
                continue;
            }

            // Skip validation for non-required empty fields
            if (empty($value) && !$isRequired) {
                continue;
            }

            // Validate type
            $type = $rule['type'] ?? 'string';
            $isValidType = $this->validateType($value, $type);
            if (!$isValidType) {
                $errors[$fieldName][] = "Tipo inválido, esperado: {$type}";
                continue;
            }

            // Validate minimum length
            if (isset($rule['min'])) {
                $minLength = $rule['min'];
                $valueLength = strlen((string) $value);
                if ($valueLength < $minLength) {
                    $errors[$fieldName][] = "Mínimo de {$minLength} caracteres";
                }
            }

            // Validate maximum length
            if (isset($rule['max'])) {
                $maxLength = $rule['max'];
                $valueLength = strlen((string) $value);
                if ($valueLength > $maxLength) {
                    $errors[$fieldName][] = "Máximo de {$maxLength} caracteres";
                }
            }

            // Validate select options
            if ($type === 'select' && isset($rule['options'])) {
                $validOptions = array_column($rule['options'], 'value');
                $isValidOption = in_array($value, $validOptions, true);
                if (!$isValidOption) {
                    $errors[$fieldName][] = "Opção inválida";
                }
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }

    private function validateType(mixed $value, string $type): bool
    {
        return match ($type) {
            'string' => is_string($value),
            'integer' => is_int($value) || (is_string($value) && is_numeric($value)),
            'number' => is_numeric($value),
            'boolean' => is_bool($value),
            'select' => is_string($value) || is_int($value),
            default => true,
        };
    }
}
