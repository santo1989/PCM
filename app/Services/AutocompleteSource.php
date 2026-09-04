<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Feeds the "remember previous entries" datalist inputs used across entry forms.
 *
 * Returns the most-used distinct values for a given model column, in one query
 * (grouped + counted, not a per-value count() loop), cached briefly since the
 * underlying data only changes when a new entry is submitted.
 */
class AutocompleteSource
{
    public static function values(string $modelClass, string $column, int $limit = 50): array
    {
        $cacheKey = 'autocomplete:' . str_replace('\\', '.', $modelClass) . ":{$column}:{$limit}";

        return Cache::remember($cacheKey, now()->addMinutes(30), function () use ($modelClass, $column, $limit) {
            /** @var \Illuminate\Database\Eloquent\Model $model */
            $model = new $modelClass();

            return $model->newQuery()
                ->select($column)
                ->whereNotNull($column)
                ->where($column, '!=', '')
                ->groupBy($column)
                ->orderByRaw('COUNT(*) DESC')
                ->limit($limit)
                ->pluck($column)
                ->all();
        });
    }

    /**
     * Call after a model of this type is created/updated so the datalist reflects
     * the new value immediately instead of waiting out the cache TTL.
     */
    public static function forget(string $modelClass, string $column, int $limit = 50): void
    {
        Cache::forget('autocomplete:' . str_replace('\\', '.', $modelClass) . ":{$column}:{$limit}");
    }
}
