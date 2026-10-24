<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

/**
 * Feeds the "remember previous entries" datalist inputs used across entry forms.
 *
 * Returns every distinct value for a model column, most-used first (ties
 * alphabetical), from one grouped query. The cache key carries the table's
 * row count + latest updated_at, so any insert/edit/delete refreshes the list
 * immediately without every save path having to clear it.
 */
class AutocompleteSource
{
    public static function values(string $modelClass, string $column): array
    {
        /** @var \Illuminate\Database\Eloquent\Model $model */
        $model = new $modelClass();
        $stamp = $model->newQuery()->toBase()->selectRaw('COUNT(*) as c, MAX(updated_at) as m')->first();
        $cacheKey = 'autocomplete:' . str_replace('\\', '.', $modelClass) . ":{$column}:" . md5($stamp->c . '|' . $stamp->m);

        return Cache::remember($cacheKey, now()->addDay(), function () use ($model, $column) {
            return $model->newQuery()
                ->select($column)
                ->whereNotNull($column)
                ->where($column, '!=', '')
                ->groupBy($column)
                ->orderByRaw('COUNT(*) DESC')
                ->orderBy($column)
                ->pluck($column)
                ->all();
        });
    }

    /**
     * Reorder a [value => label] option list so the values used most in
     * $modelClass.$column come first; unused options keep their original order.
     */
    public static function ranked(array $options, string $modelClass, string $column): array
    {
        $rank = array_flip(self::values($modelClass, $column));
        $original = array_flip(array_keys($options));
        uksort($options, fn($a, $b) => [$rank[$a] ?? PHP_INT_MAX, $original[$a]] <=> [$rank[$b] ?? PHP_INT_MAX, $original[$b]]);

        return $options;
    }
}
