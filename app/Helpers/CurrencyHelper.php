<?php

namespace App\Helpers;

/**
 * Utility helper to handle currency conversions for aggregates and statistics.
 */
class CurrencyHelper
{
    /**
     * Convert an amount from a given currency to a target currency (default: USD).
     */
    public static function convert(float $amount, string $from, string $to = 'USD'): float
    {
        $from = strtoupper($from);
        $to = strtoupper($to);

        if ($from === $to) {
            return $amount;
        }

        $rates = [
            'USD' => (float) env('CURRENCY_RATE_USD', 1.0),
            'SAR' => (float) env('CURRENCY_RATE_SAR', 3.75),
            'AED' => (float) env('CURRENCY_RATE_AED', 3.67),
            'SYP' => (float) env('CURRENCY_RATE_SYP', 15000.0),
        ];

        $fromRate = $rates[$from] ?? 1.0;
        $toRate = $rates[$to] ?? 1.0;

        if ($fromRate <= 0) $fromRate = 1.0;
        if ($toRate <= 0) $toRate = 1.0;

        // Convert to USD base first
        $usdAmount = $amount / $fromRate;

        // Convert from USD to target currency
        return $usdAmount * $toRate;
    }

    /**
     * Convert an amount directly to USD.
     */
    public static function toUsd(float $amount, ?string $from): float
    {
        if (!$from) {
            return $amount;
        }
        return self::convert($amount, $from, 'USD');
    }
}
