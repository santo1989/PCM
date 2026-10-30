<?php

namespace App\Services\Analytics;

/**
 * Small, dependency-free time-series toolkit for monthly money series
 * (arrays of floats, oldest first). Used by BudgetPredictor.
 *
 * Forecasters: seasonal naive, weighted moving average, simple exponential
 * smoothing, damped-trend Holt, additive Holt-Winters (12-month season) and
 * Croston/SBA for intermittent (often-zero) spending. ensemble() back-tests
 * every model on the last few months (rolling origin), weights them by
 * 1/MAE^2 and derives an 80% interval from the back-test errors.
 */
final class TimeSeries
{
    public static function median(array $x): float
    {
        if (!$x) {
            return 0.0;
        }
        $x = array_values($x);
        sort($x);
        $n = count($x);

        return $n % 2 ? (float) $x[intdiv($n, 2)] : ($x[$n / 2 - 1] + $x[$n / 2]) / 2;
    }

    /** Linear-interpolated quantile, q in [0, 1]. */
    public static function quantile(array $x, float $q): float
    {
        if (!$x) {
            return 0.0;
        }
        $x = array_values($x);
        sort($x);
        $pos = (count($x) - 1) * $q;
        $lo = (int) floor($pos);
        $hi = (int) ceil($pos);

        return $x[$lo] + ($x[$hi] - $x[$lo]) * ($pos - $lo);
    }

    /** Scaled median absolute deviation (a robust standard deviation). */
    public static function mad(array $x): float
    {
        $m = self::median($x);

        return 1.4826 * self::median(array_map(fn($v) => abs($v - $m), $x));
    }

    /** Hampel filter: points more than $k robust SDs from the local median become that median. */
    public static function hampel(array $y, int $half = 3, float $k = 3.0): array
    {
        $out = $y;
        $n = count($y);
        for ($i = 0; $i < $n; $i++) {
            $window = array_slice($y, max(0, $i - $half), 2 * $half + 1);
            $m = self::median($window);
            // MAD is 0 when neighbours are identical; floor it. m = 0 means intermittent spend: leave it.
            $s = max(self::mad($window), 0.5 * $m / $k);
            $limit = $k * $s;
            // A peak that also happened in the same month a year before/after is seasonal, not an outlier.
            $seasonal = (isset($y[$i - 12]) && abs($y[$i - 12] - $m) > $limit && ($y[$i - 12] - $m) * ($y[$i] - $m) > 0)
                || (isset($y[$i + 12]) && abs($y[$i + 12] - $m) > $limit && ($y[$i + 12] - $m) * ($y[$i] - $m) > 0);
            if ($m > 0 && abs($y[$i] - $m) > $limit && !$seasonal) {
                $out[$i] = $m;
            }
        }

        return $out;
    }

    /** Theil-Sen slope: median of all pairwise slopes, robust to outliers. */
    public static function theilSen(array $y): float
    {
        $slopes = [];
        $n = count($y);
        for ($i = 0; $i < $n; $i++) {
            for ($j = $i + 1; $j < $n; $j++) {
                $slopes[] = ($y[$j] - $y[$i]) / ($j - $i);
            }
        }

        return self::median($slopes);
    }

    /** Standard normal CDF (Abramowitz-Stegun 7.1.26, error < 1.5e-7). */
    public static function normalCdf(float $z): float
    {
        $t = 1 / (1 + 0.3275911 * abs($z) / M_SQRT2);
        $erf = 1 - ((((1.061405429 * $t - 1.453152027) * $t + 1.421413741) * $t - 0.284496736) * $t + 0.254829592) * $t * exp(-$z * $z / 2);

        return $z >= 0 ? (1 + $erf) / 2 : (1 - $erf) / 2;
    }

    // ---- one-step-ahead forecasters (null = not enough history) ----

    public static function seasonalNaive(array $y): ?float
    {
        $n = count($y);
        if ($n < 24) {
            return null;
        }

        // Same month last year, scaled by how the last 12 months compare with the 12 before.
        $recent = array_sum(array_slice($y, -12));
        $prior = array_sum(array_slice($y, -24, 12));

        return $y[$n - 12] * ($prior > 0 ? max(0.5, min(2.0, $recent / $prior)) : 1.0);
    }

    public static function wma(array $y, int $span = 6): ?float
    {
        $tail = array_slice($y, -$span);
        if (!$tail) {
            return null;
        }
        $sum = $w = 0;
        foreach ($tail as $i => $v) {
            $sum += ($i + 1) * $v;
            $w += $i + 1;
        }

        return $sum / $w;
    }

    /** SES with alpha chosen by in-sample one-step squared error. */
    public static function ses(array $y): ?float
    {
        if (count($y) < 3) {
            return null;
        }

        return self::bestFit([0.1, 0.2, 0.3, 0.5, 0.7], function ($a) use ($y) {
            $level = $y[0];
            $sse = 0;
            foreach (array_slice($y, 1) as $v) {
                $sse += ($v - $level) ** 2;
                $level = $a * $v + (1 - $a) * $level;
            }

            return [$sse, $level];
        });
    }

    /** Holt's linear trend with damping (phi 0.9) so trends don't run away. */
    public static function holtDamped(array $y): ?float
    {
        if (count($y) < 6) {
            return null;
        }
        $phi = 0.9;
        $grid = [];
        foreach ([0.2, 0.4, 0.6] as $a) {
            foreach ([0.05, 0.15] as $b) {
                $grid[] = [$a, $b];
            }
        }

        return self::bestFit($grid, function ($p) use ($y, $phi) {
            [$a, $b] = $p;
            $level = $y[0];
            $trend = $y[1] - $y[0];
            $sse = 0;
            foreach (array_slice($y, 1) as $v) {
                $f = $level + $phi * $trend;
                $sse += ($v - $f) ** 2;
                $newLevel = $a * $v + (1 - $a) * $f;
                $trend = $b * ($newLevel - $level) + (1 - $b) * $phi * $trend;
                $level = $newLevel;
            }

            return [$sse, $level + $phi * $trend];
        });
    }

    /** Additive Holt-Winters, 12-month season; needs two full years. */
    public static function holtWinters(array $y, int $m = 12): ?float
    {
        $n = count($y);
        if ($n < 2 * $m) {
            return null;
        }
        $grid = [];
        foreach ([0.2, 0.5] as $a) {
            foreach ([0.1, 0.3] as $g) {
                $grid[] = [$a, 0.05, $g];
            }
        }

        return self::bestFit($grid, function ($p) use ($y, $m, $n) {
            [$a, $b, $g] = $p;
            $level = array_sum(array_slice($y, 0, $m)) / $m;
            $trend = (array_sum(array_slice($y, $m, $m)) / $m - $level) / $m;
            $season = [];
            for ($i = 0; $i < $m; $i++) {
                $season[$i] = $y[$i] - $level;
            }
            $sse = 0;
            for ($t = $m; $t < $n; $t++) {
                $s = $season[$t % $m];
                $sse += ($y[$t] - ($level + $trend + $s)) ** 2;
                $newLevel = $a * ($y[$t] - $s) + (1 - $a) * ($level + $trend);
                $trend = $b * ($newLevel - $level) + (1 - $b) * $trend;
                $season[$t % $m] = $g * ($y[$t] - $newLevel) + (1 - $g) * $s;
                $level = $newLevel;
            }

            return [$sse, $level + $trend + $season[$n % $m]];
        });
    }

    /** Croston with the Syntetos-Boylan correction: for categories with many zero months. */
    public static function croston(array $y): ?float
    {
        $nonZero = array_filter($y, fn($v) => $v > 0);
        if (count($nonZero) < 2) {
            return null;
        }
        $a = 0.2;
        $size = $interval = null;
        $gap = 1;
        foreach ($y as $v) {
            if ($v > 0) {
                $size = $size === null ? $v : $a * $v + (1 - $a) * $size;
                $interval = $interval === null ? $gap : $a * $gap + (1 - $a) * $interval;
                $gap = 1;
            } else {
                $gap++;
            }
        }

        return (1 - $a / 2) * $size / $interval;
    }

    /** Run $fit for every parameter set; return the forecast of the lowest-SSE one. */
    private static function bestFit(array $grid, callable $fit): float
    {
        $best = null;
        foreach ($grid as $p) {
            [$sse, $forecast] = $fit($p);
            if ($best === null || $sse < $best[0]) {
                $best = [$sse, $forecast];
            }
        }

        return $best[1];
    }

    public const MODELS = ['seasonalNaive', 'wma', 'ses', 'holtDamped', 'holtWinters', 'croston'];

    /**
     * Back-test each model on the last $test months (refit at every origin),
     * weight by 1/MAE^2 and forecast the next month.
     * Returns [forecast, lo, hi, method, mae, weights, errors]; lo/hi = 80% interval.
     */
    public static function ensemble(array $y, int $test = 6): array
    {
        $y = array_values(array_map('floatval', $y));
        $n = count($y);
        $empty = ['forecast' => 0.0, 'lo' => 0.0, 'hi' => 0.0, 'method' => 'none', 'mae' => null, 'weights' => [], 'errors' => [], 'actuals' => [], 'bias' => 0.0];
        if ($n === 0 || max($y) <= 0) {
            return $empty;
        }

        $clean = self::hampel($y);
        $test = min($test, max(0, $n - 4));
        $errors = $mae = [];
        foreach (self::MODELS as $model) {
            $e = [];
            for ($t = $n - $test; $t < $n; $t++) {
                $f = self::$model(array_slice($clean, 0, $t));
                if ($f !== null) {
                    $e[$t] = $y[$t] - max(0, $f);
                }
            }
            if (count($e) >= max(1, $test - 1) && self::$model($clean) !== null) {
                $errors[$model] = $e;
                $mae[$model] = array_sum(array_map('abs', $e)) / count($e);
            }
        }

        if (!$mae) {
            $f = max(0, self::wma($clean) ?? 0);

            return ['forecast' => $f, 'lo' => $f, 'hi' => $f, 'method' => 'wma'] + $empty;
        }

        $weights = [];
        foreach ($mae as $model => $m) {
            $weights[$model] = 1 / max($m, 1) ** 2;
        }
        $total = array_sum($weights);
        $forecast = 0;
        foreach ($weights as $model => $w) {
            $weights[$model] = $w / $total;
            $forecast += $weights[$model] * max(0, self::$model($clean));
        }

        // shortcut: interval uses back-test errors of the weighted mix with the final weights
        // (slightly optimistic); switch to nested back-tests if intervals prove too narrow.
        $mixErrors = $actuals = [];
        foreach (array_keys(reset($errors)) as $t) {
            $actuals[] = $y[$t];
            $e = 0;
            foreach ($weights as $model => $w) {
                $e += $w * ($errors[$model][$t] ?? 0);
            }
            $mixErrors[] = $e;
        }
        arsort($weights);

        // Learned bias: if the mix keeps missing in one direction, shift by the median miss.
        $bias = count($mixErrors) >= 4 ? self::median($mixErrors) : 0.0;
        $forecast = max(0, $forecast + $bias);
        $centred = array_map(fn($e) => $e - $bias, $mixErrors);

        return [
            'forecast' => $forecast,
            'lo' => max(0, $forecast + min(0, self::quantile($centred, 0.1))),
            'hi' => $forecast + max(0, self::quantile($centred, 0.9)),
            'bias' => $bias,
            'method' => array_key_first($weights),
            'mae' => array_sum(array_map('abs', $mixErrors)) / max(1, count($mixErrors)),
            'weights' => $weights,
            'errors' => $mixErrors,
            'actuals' => $actuals,
        ];
    }
}
