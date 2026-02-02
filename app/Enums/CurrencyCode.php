<?php

namespace App\Enums;

use NumberFormatter;

/**
 * Currency codes based on ISO 4217.
 *
 * This enum centralizes currency-related domain logic such as:
 * - Human-readable labels
 * - Currency symbols
 * - Locale-based number formatting
 * - Form options for UI layers
 */
enum CurrencyCode: string
{
    case USD = 'USD';
    case EUR = 'EUR';
    case GBP = 'GBP';
    case JPY = 'JPY';
    case CAD = 'CAD';
    case AUD = 'AUD';
    case CHF = 'CHF';
    case CNY = 'CNY';
    case INR = 'INR';
    case MXN = 'MXN';
    case BRL = 'BRL';

    /**
     * Get the human-readable label for the currency.
     *
     * @return string
     *
     * @example
     * CurrencyCode::USD->label(); // "US Dollar"
     * CurrencyCode::BRL->label(); // "Brazilian Real"
     */
    public function label(): string
    {
        return match ($this) {
            static::USD => 'US Dollar',
            static::EUR => 'Euro',
            static::GBP => 'British Pound',
            static::JPY => 'Japanese Yen',
            static::CAD => 'Canadian Dollar',
            static::AUD => 'Australian Dollar',
            static::CHF => 'Swiss Franc',
            static::CNY => 'Chinese Yuan',
            static::INR => 'Indian Rupee',
            static::MXN => 'Mexican Peso',
            static::BRL => 'Brazilian Real',
        };
    }

    /**
     * Get the currency symbol.
     *
     * @return string
     *
     * @example
     * CurrencyCode::USD->symbol(); // "$"
     * CurrencyCode::BRL->symbol(); // "R$"
     */
    public function symbol(): string
    {
        return match ($this) {
            static::USD => '$',
            static::EUR => '€',
            static::GBP => '£',
            static::JPY => '¥',
            static::CAD => 'CA$',
            static::AUD => 'A$',
            static::CHF => 'CHF',
            static::CNY => '¥',
            static::INR => '₹',
            static::MXN => '$',
            static::BRL => 'R$',
        };
    }

    /**
     * Get the default locale used to format currency values.
     *
     * @return string
     *
     * @example
     * CurrencyCode::USD->locale(); // "en_US"
     * CurrencyCode::BRL->locale(); // "pt_BR"
     */
    public function locale(): string
    {
        return match ($this) {
            static::USD => 'en_US',
            static::EUR => 'de_DE',
            static::GBP => 'en_GB',
            static::JPY => 'ja_JP',
            static::CAD => 'en_CA',
            static::AUD => 'en_AU',
            static::CHF => 'de_CH',
            static::CNY => 'zh_CN',
            static::INR => 'en_IN',
            static::MXN => 'es_MX',
            static::BRL => 'pt_BR',
        };
    }

    /**
     * Format a numeric amount according to the currency locale.
     *
     * @param float|int $amount       The numeric value to be formatted.
     * @param bool      $withSymbol   Whether to include the currency symbol.
     * @param int       $decimals     Number of decimal places.
     *
     * @return string
     *
     * @example
     * CurrencyCode::USD->format(1234.56);
     * // "$1,234.56"
     *
     * CurrencyCode::BRL->format(1234.56);
     * // "R$ 1.234,56"
     *
     * CurrencyCode::BRL->format(1234.56, false);
     * // "1.234,56"
     */
    public function format(
        float|int $amount,
        bool $withSymbol = true,
        int $decimals = 2
    ): string {
        $formatter = new NumberFormatter(
            $this->locale(),
            $withSymbol
                ? NumberFormatter::CURRENCY
                : NumberFormatter::DECIMAL
        );

        $formatter->setAttribute(
            NumberFormatter::FRACTION_DIGITS,
            $decimals
        );

        if ($withSymbol) {
            return $formatter->formatCurrency($amount, $this->value);
        }

        return $formatter->format($amount);
    }

    /**
     * Get an associative array of currency values and labels.
     *
     * Useful for form inputs.
     *
     * @return array<string, string>
     *
     * @example
     * CurrencyCode::labels();
     * // [
     * //   "USD" => "US Dollar",
     * //   "EUR" => "Euro",
     * //   ...
     * // ]
     */
    public static function labels(): array
    {
        $labels = [];

        foreach (static::cases() as $case) {
            $labels[$case->value] = $case->label();
        }

        return $labels;
    }

    /**
     * Get an array of options ready for select components.
     *
     * @return array<int, array{value: string, label: string, symbol: string}>
     *
     * @example
     * CurrencyCode::toSelectOptions();
     * // [
     * //   ["value" => "USD", "label" => "US Dollar", "symbol" => "$"],
     * //   ["value" => "BRL", "label" => "Brazilian Real", "symbol" => "R$"],
     * // ]
     */
    public static function toSelectOptions(): array
    {
        $options = [];

        foreach (static::cases() as $case) {
            $options[] = [
                'value' => $case->value,
                'label' => $case->label(),
                'symbol' => $case->symbol(),
            ];
        }

        return $options;
    }
}
