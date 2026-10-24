<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\ExpenseCalculation;
use App\Models\HandCash;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/** JSON results for the Ctrl+K search palette. */
class SearchController extends Controller
{
    public function __invoke(Request $request)
    {
        $q = trim((string) $request->get('q'));
        if (mb_strlen($q) < 1) {
            return response()->json(['groups' => []]);
        }
        $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q) . '%';
        $needle = mb_strtolower($q);

        // Pages from the shared navigation config, filtered by what this user may see.
        $pages = [];
        foreach (config('navigation.groups') as $group) {
            if (!empty($group['can']) && Gate::denies($group['can'])) {
                continue;
            }
            foreach ($group['items'] as $item) {
                if (str_contains(mb_strtolower($item['label'] . ' ' . $group['label']), $needle)) {
                    $pages[$item['route']] = [
                        'title' => $item['label'],
                        'subtitle' => $group['label'],
                        'url' => route($item['route']),
                        'icon' => $item['icon'],
                    ];
                }
            }
        }

        $groups = [['label' => 'Pages', 'items' => array_values(array_slice($pages, 0, 6))]];

        if (Gate::allows('Admin')) {
            $groups[] = [
                'label' => 'Income & expenses',
                'items' => ExpenseCalculation::with('category:id,name')
                    ->where('name', 'like', $like)
                    ->orderByDesc('date')->orderByDesc('id')
                    ->limit(8)
                    ->get(['id', 'name', 'amount', 'date', 'types', 'category_id'])
                    ->map(fn($e) => [
                        'title' => $e->name,
                        'subtitle' => trim(($e->category->name ?? '') . ' · ' . $e->date, ' ·'),
                        'meta' => ($e->types === 'INCOME' ? '+' : '−') . number_format((float) $e->amount, 2),
                        'url' => route('expenseCalculations.index', ['name' => $e->name]),
                        'icon' => $e->types === 'INCOME' ? 'bi-arrow-down-left-circle' : 'bi-arrow-up-right-circle',
                    ])->all(),
            ];

            $groups[] = [
                'label' => 'Cash & accounts',
                'items' => HandCash::where('name', 'like', $like)
                    ->orderByDesc('date')->orderByDesc('id')
                    ->limit(6)
                    ->get(['id', 'name', 'amount', 'date', 'types', 'rules'])
                    ->map(fn($h) => [
                        'title' => $h->name,
                        'subtitle' => str_replace('_', ' ', $h->rules) . ' · ' . $h->date,
                        'meta' => ($h->types === 'SAVE' ? '+' : '−') . number_format((float) $h->amount, 2),
                        'url' => route('handCashes.edit', ['handCash' => $h->id]),
                        'icon' => 'bi-wallet2',
                    ])->all(),
            ];

            $groups[] = [
                'label' => 'Categories',
                'items' => Category::where('name', 'like', $like)
                    ->orderBy('name')
                    ->limit(5)
                    ->get(['id', 'name', 'types'])
                    ->map(fn($c) => [
                        'title' => $c->name,
                        'subtitle' => ucfirst(strtolower($c->types)),
                        'url' => route('categories.edit', ['category' => $c->id]),
                        'icon' => 'bi-tag',
                    ])->all(),
            ];
        }

        return response()->json(['groups' => $groups]);
    }
}
