<?php

namespace App\Services\Analytics;

/**
 * Turns taka from any month into today's taka using config/cpi.php (Bangladesh CPI inflation).
 * real = nominal * factor(month); factor is 1 for the current month and above 1 for older months.
 */
final class Inflation
{
    private static ?array $factors = null;

    public static function factor(string $ym): float
    {
        self::$factors ??= self::factors(config('cpi.rates', []), now()->format('Y-m'));
        if (!self::$factors) {
            return 1.0;
        }
        $ym = max($ym, array_key_first(self::$factors)); // older than the table: use its first month
        return self::$factors[$ym] ?? 1.0; // future months are already in today's money
    }

    /**
     * Monthly index I(m) = I(m-1) * (1 + rate(m)/100)^(1/12), carried to $until with the last
     * rate; returns month => I($until) / I(month).
     */
    public static function factors(array $rates, string $until): array
    {
        if (!$rates) {
            return [];
        }
        ksort($rates);
        $index = [];
        $level = 100.0;
        $rate = reset($rates);
        foreach (LedgerSeries::months(array_key_first($rates) . '-01', max($until, array_key_last($rates)) . '-01') as $ym) {
            $rate = $rates[$ym] ?? $rate;
            $level *= (1 + $rate / 100) ** (1 / 12);
            $index[$ym] = $level;
        }
        $now = $index[$until] ?? end($index);
        return array_map(fn($i) => $now / $i, $index);
    }
}
