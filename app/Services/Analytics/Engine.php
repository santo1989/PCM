<?php

namespace App\Services\Analytics;

/**
 * Dependency-free analytics engine for monthly money series (plain PHP arrays, oldest first).
 *  - forecasting: candidates are back-tested on a rolling origin (expanding window, 1-3 months
 *    ahead); the lowest MASE wins, falling back to seasonal naive when nothing beats it (MASE >= 1);
 *    80/95% intervals come from the back-test errors, with forward-tested coverage
 *  - classical additive decomposition (centred 12-month trend + averaged month effects)
 *  - unusual months, change-points (optimal partitioning, L2 cost) and unusual transactions
 *    (robust z within category, confirmed by an isolation forest)
 * Random parts use a fixed seed so reruns match. RunAnalytics owns all database access.
 */
final class Engine
{
    public const H = 3;
    private const MIN_TRAIN = 24;
    private const MIN_FOLDS = 6;
    private const MAX_FOLDS = 12;
    public const SEED = 42;

    /** @param array $payload months, X (event regressors per month + H future rows), series, changepoint_series, transactions */
    public static function run(array $payload): array
    {
        $n = count($payload['months']);
        $Xh = array_slice($payload['X'], 0, $n);
        $Xf = array_slice($payload['X'], $n, self::H);
        $out = ['series' => [], 'transactions' => self::transactionAnomalies($payload['transactions'] ?? [])];
        foreach ($payload['series'] as $name => $values) {
            $y = array_map('floatval', array_values($values));
            $out['series'][] = self::runSeries($name, $y, array_slice($Xh, count($Xh) - count($y)), $Xf, in_array($name, $payload['changepoint_series'] ?? [], true));
        }
        return $out;
    }

    // ---------- candidate models: fn(y, h, X, Xf) => float[h] (null = cannot fit) ----------

    public static function candidates(bool $intermittent): array
    {
        $c = ['Seasonal Naive' => fn($y, $h) => self::seasonalNaive($y, $h)];
        if ($intermittent) {
            return $c + [
                'Croston' => fn($y, $h) => self::croston($y, $h, 1.0),
                'SBA' => fn($y, $h) => self::croston($y, $h, 0.95), // Syntetos-Boylan: (1 - alpha/2), alpha = 0.1
                'TSB' => fn($y, $h) => self::tsb($y, $h),
                'ADIDA' => fn($y, $h) => self::adida($y, $h),
            ];
        }
        return $c + [
            'ETS(A,N,N)' => fn($y, $h) => self::ses($y, $h),
            'ETS(A,Ad,N)' => fn($y, $h) => self::holtDamped($y, $h),
            'ETS(A,A,A)' => fn($y, $h) => self::holtWinters($y, $h),
            'Theta' => fn($y, $h) => self::theta($y, $h),
            'Linear + Eid' => fn($y, $h, $X, $Xf) => self::linearEvents($y, $h, $X, $Xf),
        ];
    }

    public static function seasonalNaive(array $y, int $h): ?array
    {
        $n = count($y);
        if ($n < 12) {
            return null;
        }
        $out = [];
        for ($k = 0; $k < $h; $k++) {
            $out[] = $y[$n - 12 + $k % 12];
        }
        return $out;
    }

    /** Simple exponential smoothing; alpha by in-sample one-step SSE. */
    public static function ses(array $y, int $h): ?array
    {
        if (count($y) < 3) {
            return null;
        }
        [, $level] = self::sesFit($y);
        return array_fill(0, $h, $level);
    }

    /** [alpha, final level] with the lowest one-step SSE. */
    private static function sesFit(array $y): array
    {
        $best = null;
        foreach ([0.05, 0.1, 0.2, 0.3, 0.5, 0.7, 0.9] as $a) {
            $level = $y[0];
            $sse = 0;
            for ($t = 1, $n = count($y); $t < $n; $t++) {
                $sse += ($y[$t] - $level) ** 2;
                $level = $a * $y[$t] + (1 - $a) * $level;
            }
            if ($best === null || $sse < $best[0]) {
                $best = [$sse, $a, $level];
            }
        }
        return [$best[1], $best[2]];
    }

    /** Damped-trend Holt. */
    public static function holtDamped(array $y, int $h): ?array
    {
        if (count($y) < 6) {
            return null;
        }
        $best = null;
        foreach ([0.1, 0.2, 0.4, 0.6] as $a) {
            foreach ([0.05, 0.15] as $b) {
                foreach ([0.8, 0.9, 0.98] as $phi) {
                    $level = $y[0];
                    $trend = $y[1] - $y[0];
                    $sse = 0;
                    for ($t = 1, $n = count($y); $t < $n; $t++) {
                        $f = $level + $phi * $trend;
                        $sse += ($y[$t] - $f) ** 2;
                        $new = $a * $y[$t] + (1 - $a) * $f;
                        $trend = $b * ($new - $level) + (1 - $b) * $phi * $trend;
                        $level = $new;
                    }
                    if ($best === null || $sse < $best[0]) {
                        $best = [$sse, $level, $trend, $phi];
                    }
                }
            }
        }
        [, $level, $trend, $phi] = $best;
        $out = [];
        for ($k = 1, $damp = 0; $k <= $h; $k++) {
            $damp += $phi ** $k;
            $out[] = $level + $damp * $trend;
        }
        return $out;
    }

    /** Additive Holt-Winters, 12-month season; needs two full years. */
    public static function holtWinters(array $y, int $h, int $m = 12): ?array
    {
        $n = count($y);
        if ($n < 2 * $m) {
            return null;
        }
        $best = null;
        foreach ([0.1, 0.3, 0.5] as $a) {
            foreach ([0.01, 0.05] as $b) {
                foreach ([0.1, 0.3] as $g) {
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
                        $new = $a * ($y[$t] - $s) + (1 - $a) * ($level + $trend);
                        $trend = $b * ($new - $level) + (1 - $b) * $trend;
                        $season[$t % $m] = $g * ($y[$t] - $new) + (1 - $g) * $s;
                        $level = $new;
                    }
                    if ($best === null || $sse < $best[0]) {
                        $best = [$sse, $level, $trend, $season];
                    }
                }
            }
        }
        [, $level, $trend, $season] = $best;
        $out = [];
        for ($k = 1; $k <= $h; $k++) {
            $out[] = $level + $k * $trend + $season[($n + $k - 1) % $m];
        }
        return $out;
    }

    /** Theta method: SES plus half the linear-regression drift. */
    public static function theta(array $y, int $h): ?array
    {
        $n = count($y);
        if ($n < 6) {
            return null;
        }
        [$a, $level] = self::sesFit($y);
        $slope = self::olsSlope($y);
        $out = [];
        for ($k = 1; $k <= $h; $k++) {
            $out[] = $level + $slope / 2 * (($k - 1) + 1 / $a - (1 - $a) ** $n / $a);
        }
        return $out;
    }

    /** Least squares on [1, t, Ramadan share, Eid-ul-Fitr, Eid-ul-Adha]; tiny ridge so absent events stay 0. */
    public static function linearEvents(array $y, int $h, array $X, array $Xf): ?array
    {
        $n = count($y);
        if ($n < 12) {
            return null;
        }
        $row = fn($t, $x) => array_merge([1.0, (float) $t], array_map('floatval', $x));
        $A = [];
        foreach ($y as $t => $_) {
            $A[] = $row($t, $X[$t]);
        }
        $coef = self::leastSquares($A, $y, 1e-6);
        $out = [];
        for ($k = 0; $k < $h; $k++) {
            $r = $row($n + $k, $Xf[$k]);
            $out[] = array_sum(array_map(fn($a, $b) => $a * $b, $r, $coef));
        }
        return $out;
    }

    /** Croston's method; $correction 1.0 = classic, 0.95 = SBA. Flat forecast. */
    public static function croston(array $y, int $h, float $correction, float $a = 0.1): ?array
    {
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
        return $size === null ? null : array_fill(0, $h, $correction * $size / $interval);
    }

    /** Teunter-Syntetos-Babai: smooths the chance of a spend every month, so it decays when spending stops. */
    public static function tsb(array $y, int $h, float $ad = 0.2, float $ap = 0.2): ?array
    {
        $first = null;
        foreach ($y as $v) {
            if ($v > 0) {
                $first = $v;
                break;
            }
        }
        if ($first === null) {
            return null;
        }
        $p = count(array_filter($y, fn($v) => $v > 0)) / count($y);
        $z = $first;
        foreach ($y as $v) {
            $p += $ad * (($v > 0 ? 1 : 0) - $p);
            if ($v > 0) {
                $z += $ap * ($v - $z);
            }
        }
        return array_fill(0, $h, $p * $z);
    }

    /** ADIDA: sum into buckets of the mean gap between spends, smooth, spread back per month. */
    public static function adida(array $y, int $h): ?array
    {
        $nonZero = count(array_filter($y, fn($v) => $v > 0));
        if ($nonZero === 0) {
            return null;
        }
        $size = max(1, (int) round(count($y) / $nonZero));
        $buckets = array_map('array_sum', array_chunk(array_slice($y, count($y) % $size), $size));
        if (count($buckets) < 3) {
            return null;
        }
        [, $level] = self::sesFit($buckets);
        return array_fill(0, $h, $level / $size);
    }

    // ---------- back-test and selection ----------

    public static function runSeries(string $name, array $y, array $X, array $Xf, bool $wantChangepoints): array
    {
        $first = 0;
        while ($first < count($y) && $y[$first] <= 0) {
            $first++;
        }
        // Leading zeros = category not used yet, not real zeros.
        $y = array_slice($y, $first);
        $X = array_slice($X, $first);
        $n = count($y);
        $res = ['name' => $name, 'offset' => $first, 'n' => $n, 'models' => [], 'chosen' => null, 'note' => null,
            'forecast' => [], 'decomposition' => null, 'anomalies' => [], 'changepoints' => [], 'intermittent' => false];
        $nonZero = count(array_filter($y, fn($v) => $v > 0));
        $res['intermittent'] = $intermittent = $nonZero > 0 && $n / $nonZero > 1.32;

        if ($nonZero === 0 || array_sum(array_slice($y, -12)) == 0) {
            $res['note'] = 'no spend in the last 12 months';
            return $res;
        }
        $folds = min(self::MAX_FOLDS, $n - self::H - self::MIN_TRAIN + 1);
        if ($folds < self::MIN_FOLDS) {
            $res['note'] = sprintf('not enough history to forecast (%d months; needs %d)', $n, self::MIN_TRAIN + self::H + self::MIN_FOLDS - 1);
            $res['average'] = array_sum(array_slice($y, -12)) / min(12, $n);
            return $res;
        }

        $fns = self::candidates($intermittent);
        $origins = range($n - self::H - $folds + 1, $n - self::H);
        $actual = array_map(fn($t) => array_slice($y, $t, self::H), $origins);
        $scales = array_map(fn($t) => self::maseScale(array_slice($y, 0, $t)), $origins);
        $preds = [];
        foreach ($fns as $k => $f) {
            $rows = [];
            foreach ($origins as $t) {
                $p = $f(array_slice($y, 0, $t), self::H, array_slice($X, 0, $t), array_slice($X, $t, self::H));
                if ($p === null || count($p) !== self::H || array_filter($p, fn($v) => !is_finite($v))) {
                    continue 2; // a model that fails any fold is dropped
                }
                $rows[] = array_map(fn($v) => max(0, $v), $p);
            }
            $preds[$k] = $rows;
        }

        $metrics = [];
        foreach ($preds as $k => $p) {
            $metrics[$k] = self::score($actual, $scales, $p);
        }
        $top = array_slice(self::ranked($metrics), 0, 3);
        if (count($top) >= 2) {
            $preds['Ensemble'] = self::medianOf(array_map(fn($k) => $preds[$k], $top));
            $metrics['Ensemble'] = self::score($actual, $scales, $preds['Ensemble']);
        }

        $chosen = self::ranked($metrics)[0] ?? 'Seasonal Naive';
        if (($metrics[$chosen]['mase'] ?? INF) >= 1) {
            $res['note'] = sprintf('no model beat seasonal naive (best MASE %.2f), so seasonal naive is used', $metrics[$chosen]['mase'] ?? 0);
            $chosen = 'Seasonal Naive';
        }
        $res['chosen'] = $chosen;
        foreach ($metrics as $k => $m) {
            $res['models'][] = ['model' => $k] + $m + ['chosen' => $k === $chosen];
        }

        $point = $chosen === 'Ensemble'
            ? self::medianOf(array_map(fn($k) => [$fns[$k]($y, self::H, $X, $Xf)], $top))[0]
            : $fns[$chosen]($y, self::H, $X, $Xf);
        for ($h = 0; $h < self::H; $h++) {
            $err = array_map(fn($a, $p) => abs($a[$h] - $p[$h]), $actual, $preds[$chosen]);
            [$q80, $q95] = [self::quantileHigher($err, 0.8), self::quantileHigher($err, 0.95)];
            $mean = max(0, $point[$h]);
            $res['forecast'][] = ['h' => $h + 1, 'mean' => $mean, 'lo80' => max($mean - $q80, 0), 'hi80' => $mean + $q80, 'lo95' => max($mean - $q95, 0), 'hi95' => $mean + $q95];
        }

        if (!$intermittent && $n >= 24) {
            $res['decomposition'] = self::decompose($y);
            $res['anomalies'] = self::monthAnomalies($y);
        }
        if ($wantChangepoints) {
            $res['changepoints'] = self::changepoints($y);
        }
        return $res;
    }

    /** In-sample mean absolute seasonal-naive error; falls back to lag 1 when that is 0. */
    public static function maseScale(array $y): float
    {
        foreach ([12, 1] as $lag) {
            if (count($y) > $lag) {
                $d = [];
                for ($i = $lag, $n = count($y); $i < $n; $i++) {
                    $d[] = abs($y[$i] - $y[$i - $lag]);
                }
                $s = array_sum($d) / count($d);
                if ($s > 0) {
                    return $s;
                }
            }
        }
        return 0.0;
    }

    /** MASE, sMAPE, RMSE and forward coverage (each fold's interval only from earlier folds' errors, from the 5th fold on). */
    public static function score(array $actual, array $scales, array $pred): array
    {
        $abs = $mases = $sm = [];
        $sq = 0;
        foreach ($actual as $i => $a) {
            foreach ($a as $h => $v) {
                $e = $v - $pred[$i][$h];
                $abs[$i][$h] = abs($e);
                $sq += $e * $e;
                $d = abs($v) + abs($pred[$i][$h]);
                $sm[] = $d == 0 ? 0 : 200 * abs($e) / $d;
            }
            if ($scales[$i] > 0) {
                $mases[] = array_sum($abs[$i]) / count($abs[$i]) / $scales[$i];
            }
        }
        $cov = [];
        foreach ([80, 95] as $level) {
            $hits = [];
            for ($i = 5, $f = count($actual); $i < $f; $i++) {
                foreach ($abs[$i] as $h => $e) {
                    $hits[] = $e <= self::quantileHigher(array_column(array_slice($abs, 0, $i), $h), $level / 100) ? 1 : 0;
                }
            }
            $cov[$level] = $hits ? array_sum($hits) / count($hits) : null;
        }
        return ['mase' => $mases ? array_sum($mases) / count($mases) : null, 'smape' => array_sum($sm) / count($sm),
            'rmse' => sqrt($sq / count($sm)), 'cover80' => $cov[80], 'cover95' => $cov[95]];
    }

    // ---------- decomposition, anomalies, change-points ----------

    /** Classical additive decomposition: centred 2x12 moving-average trend, averaged month effects. */
    public static function decompose(array $y): array
    {
        $n = count($y);
        $trend = array_fill(0, $n, null);
        for ($i = 6; $i < $n - 6; $i++) {
            $trend[$i] = (0.5 * $y[$i - 6] + array_sum(array_slice($y, $i - 5, 11)) + 0.5 * $y[$i + 6]) / 12;
        }
        // shortcut: the first/last 6 months repeat the nearest trend value; a LOESS (STL) trend would bend there.
        for ($i = 0; $i < 6; $i++) {
            $trend[$i] = $trend[6];
            $trend[$n - 1 - $i] = $trend[$n - 7];
        }
        $byMonth = [];
        foreach ($y as $i => $v) {
            $byMonth[$i % 12][] = $v - $trend[$i];
        }
        $effect = array_map(fn($d) => array_sum($d) / count($d), $byMonth);
        $centre = array_sum($effect) / 12;
        $seasonal = $remainder = [];
        foreach ($y as $i => $v) {
            $seasonal[$i] = $effect[$i % 12] - $centre;
            $remainder[$i] = $v - $trend[$i] - $seasonal[$i];
        }
        return ['trend' => $trend, 'seasonal' => $seasonal, 'remainder' => $remainder];
    }

    /**
     * A month is unusual only when it is far from BOTH the median of the previous 12 months
     * and the same month last year, so a yearly pattern is not flagged but a new spike is.
     * Spread = MAD of the previous 12 months, floored at 10% of the typical month so a fixed
     * bill does not turn every small change into an extreme score.
     */
    public static function monthAnomalies(array $y, float $limit = 3.5): array
    {
        $floor = 0.1 * TimeSeries::median(array_filter($y, fn($v) => $v > 0));
        $out = [];
        for ($i = 12, $n = count($y); $i < $n; $i++) {
            $w = array_slice($y, $i - 12, 12);
            $med = TimeSeries::median($w);
            $scale = max(TimeSeries::mad($w), $floor);
            $expected = abs($y[$i] - $med) <= abs($y[$i] - $y[$i - 12]) ? $med : $y[$i - 12];
            $z = $scale > 0 ? ($y[$i] - $expected) / $scale : 0;
            if (abs($z) > $limit) {
                $out[] = ['idx' => $i, 'value' => $y[$i], 'expected' => $expected, 'z' => $z];
            }
        }
        return $out;
    }

    /** Lasting level shifts: optimal partitioning with L2 cost on the standardised series, penalty 2 ln n, segments >= 3 months. */
    public static function changepoints(array $y, int $minSize = 3): array
    {
        $n = count($y);
        $mean = array_sum($y) / $n;
        $sd = sqrt(array_sum(array_map(fn($v) => ($v - $mean) ** 2, $y)) / $n) ?: 1;
        $s1 = $s2 = [0.0];
        foreach ($y as $i => $v) {
            $z = ($v - $mean) / $sd;
            $s1[] = $s1[$i] + $z;
            $s2[] = $s2[$i] + $z * $z;
        }
        $cost = fn($a, $b) => $s2[$b] - $s2[$a] - ($s1[$b] - $s1[$a]) ** 2 / ($b - $a);
        $pen = 2 * log($n);
        $F = [0 => -$pen];
        $prev = [];
        for ($t = $minSize; $t <= $n; $t++) {
            $F[$t] = INF;
            foreach ($F as $s => $fs) {
                if ($s <= $t - $minSize && $fs < INF) {
                    $c = $fs + $cost($s, $t) + $pen;
                    if ($c < $F[$t]) {
                        [$F[$t], $prev[$t]] = [$c, $s];
                    }
                }
            }
        }
        $bkps = [];
        for ($t = $n; $t > 0; $t = $prev[$t]) {
            $bkps[] = $t;
        }
        $bkps = array_reverse($bkps); // segment ends, last = n
        $out = [];
        $start = 0;
        foreach (array_slice($bkps, 0, -1) as $i => $b) {
            $seg = fn($a, $c) => array_sum(array_slice($y, $a, $c - $a)) / ($c - $a);
            $out[] = ['idx' => $b, 'before' => $seg($start, $b), 'after' => $seg($b, $bkps[$i + 1])];
            $start = $b;
        }
        return $out;
    }

    /**
     * Per-category robust z of log amount, confirmed by an isolation forest over
     * [category z, log amount, weekday, day of month]. Flags z > 5 alone, or z > 3 + forest (top 1%).
     * rows: [['id', 'cat', 'date', 'amount'], ...]
     */
    public static function transactionAnomalies(array $rows): array
    {
        if (count($rows) < 50) {
            return [];
        }
        $amt = $logs = $byCat = [];
        foreach ($rows as $i => $r) {
            $amt[$i] = max((float) $r['amount'], 1.0);
            $logs[$i] = log($amt[$i]);
            $byCat[$r['cat']][] = $i;
        }
        $z = array_fill(0, count($rows), 0.0);
        $med = [];
        foreach ($byCat as $cat => $idx) {
            $med[$cat] = TimeSeries::median(array_map(fn($i) => $amt[$i], $idx));
            if (count($idx) >= 5) {
                $l = array_map(fn($i) => $logs[$i], $idx);
                $m = TimeSeries::median($l);
                $s = TimeSeries::mad($l);
                foreach ($idx as $i) {
                    $z[$i] = $s > 0 ? ($logs[$i] - $m) / $s : 0;
                }
            }
        }
        $feats = [];
        foreach ($rows as $i => $r) {
            $d = strtotime($r['date']);
            $feats[$i] = [$z[$i], $logs[$i], (int) date('N', $d), (int) date('j', $d)];
        }
        $score = self::isolationForest($feats);
        $cut = TimeSeries::quantile($score, 0.99);

        $out = [];
        foreach ($rows as $i => $r) {
            $flagged = $score[$i] >= $cut;
            if ($z[$i] > 5 || ($z[$i] > 3 && $flagged)) {
                $reason = sprintf('%.1fx the usual amount for this category (median %s)', $amt[$i] / $med[$r['cat']], number_format($med[$r['cat']]));
                $out[] = ['id' => $r['id'], 'z' => $z[$i], 'score' => $score[$i],
                    'reason' => $reason . ($flagged ? '; also unusual in day, weekday and size together' : '')];
            }
        }
        return $out;
    }

    /** Isolation forest anomaly scores in (0, 1]; higher = easier to isolate. Seeded. */
    public static function isolationForest(array $X, int $trees = 200, int $sample = 256): array
    {
        mt_srand(self::SEED);
        $n = count($X);
        $sample = min($sample, $n);
        $depthLimit = (int) ceil(log($sample, 2));
        $c = fn($m) => $m > 2 ? 2 * (log($m - 1) + 0.5772156649) - 2 * ($m - 1) / $m : ($m == 2 ? 1.0 : 0.0);
        $build = function (array $idx, int $depth) use (&$build, $X, $depthLimit) {
            if ($depth >= $depthLimit || count($idx) <= 1) {
                return ['size' => count($idx)];
            }
            $f = mt_rand(0, count($X[$idx[0]]) - 1);
            $vals = array_map(fn($i) => $X[$i][$f], $idx);
            [$lo, $hi] = [min($vals), max($vals)];
            if ($lo == $hi) {
                return ['size' => count($idx)];
            }
            $split = $lo + mt_rand() / mt_getrandmax() * ($hi - $lo);
            $left = array_values(array_filter($idx, fn($i) => $X[$i][$f] < $split));
            $right = array_values(array_filter($idx, fn($i) => $X[$i][$f] >= $split));
            return ['f' => $f, 'split' => $split, 'l' => $build($left, $depth + 1), 'r' => $build($right, $depth + 1)];
        };
        $forest = [];
        $all = range(0, $n - 1);
        for ($t = 0; $t < $trees; $t++) {
            $pick = $all;
            for ($i = 0; $i < $sample; $i++) { // partial Fisher-Yates: sample without replacement
                $j = mt_rand($i, $n - 1);
                [$pick[$i], $pick[$j]] = [$pick[$j], $pick[$i]];
            }
            $forest[] = $build(array_slice($pick, 0, $sample), 0);
        }
        $scores = [];
        foreach ($X as $i => $x) {
            $total = 0;
            foreach ($forest as $node) {
                $depth = 0;
                while (isset($node['f'])) {
                    $node = $x[$node['f']] < $node['split'] ? $node['l'] : $node['r'];
                    $depth++;
                }
                $total += $depth + $c($node['size']);
            }
            $scores[$i] = 2 ** (-($total / $trees) / $c($sample));
        }
        return $scores;
    }

    // ---------- small numeric helpers ----------

    /** Quantile that never interpolates below an observed value (numpy method="higher"). */
    public static function quantileHigher(array $x, float $q): float
    {
        $x = array_values($x);
        sort($x);
        return (float) $x[(int) ceil($q * (count($x) - 1))];
    }

    private static function olsSlope(array $y): float
    {
        $n = count($y);
        $tm = ($n - 1) / 2;
        $ym = array_sum($y) / $n;
        $num = $den = 0;
        foreach ($y as $t => $v) {
            $num += ($t - $tm) * ($v - $ym);
            $den += ($t - $tm) ** 2;
        }
        return $den > 0 ? $num / $den : 0.0;
    }

    /** Ridge-stabilised normal equations, solved by Gaussian elimination with partial pivoting. */
    private static function leastSquares(array $A, array $y, float $ridge): array
    {
        $p = count($A[0]);
        $M = array_fill(0, $p, array_fill(0, $p + 1, 0.0));
        foreach ($A as $r => $row) {
            for ($i = 0; $i < $p; $i++) {
                for ($j = 0; $j < $p; $j++) {
                    $M[$i][$j] += $row[$i] * $row[$j];
                }
                $M[$i][$p] += $row[$i] * $y[$r];
            }
        }
        for ($i = 0; $i < $p; $i++) {
            $M[$i][$i] += $ridge * max(1.0, $M[$i][$i]);
        }
        for ($c = 0; $c < $p; $c++) {
            $piv = $c;
            for ($r = $c + 1; $r < $p; $r++) {
                if (abs($M[$r][$c]) > abs($M[$piv][$c])) {
                    $piv = $r;
                }
            }
            [$M[$c], $M[$piv]] = [$M[$piv], $M[$c]];
            for ($r = 0; $r < $p; $r++) {
                if ($r !== $c && $M[$c][$c] != 0) {
                    $f = $M[$r][$c] / $M[$c][$c];
                    for ($k = $c; $k <= $p; $k++) {
                        $M[$r][$k] -= $f * $M[$c][$k];
                    }
                }
            }
        }
        return array_map(fn($i) => $M[$i][$i] != 0 ? $M[$i][$p] / $M[$i][$i] : 0.0, range(0, $p - 1));
    }

    /** Element-wise median of several equally shaped 2-D arrays. */
    private static function medianOf(array $list): array
    {
        $out = [];
        foreach ($list[0] as $i => $row) {
            foreach ($row as $h => $_) {
                $out[$i][$h] = TimeSeries::median(array_map(fn($m) => $m[$i][$h], $list));
            }
        }
        return $out;
    }

    /** Model names with a MASE, best first. */
    private static function ranked(array $metrics): array
    {
        $metrics = array_filter($metrics, fn($m) => $m['mase'] !== null);
        uasort($metrics, fn($a, $b) => $a['mase'] <=> $b['mase']);
        return array_keys($metrics);
    }
}
