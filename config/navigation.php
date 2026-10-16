<?php

/**
 * Single source of truth for app navigation. Read by the sidebar, the mobile
 * bottom bar, the report tabs (reports/partials/report_nav) and the Ctrl+K
 * search palette, so a page added here shows up everywhere at once.
 *
 *   route  named route
 *   icon   Bootstrap Icons class
 *   can    optional Gate ability required to see the link
 *   match  optional route-name pattern for the "active" state (defaults to route)
 */

return [

    'groups' => [
        [
            'label' => 'Overview',
            'can' => 'Admin',
            'items' => [
                ['route' => 'home', 'label' => 'Dashboard', 'icon' => 'bi-house-door'],
            ],
        ],
        [
            'label' => 'Money',
            'can' => 'Admin',
            'items' => [
                ['route' => 'expenseCalculations.index', 'label' => 'Income & Expenses', 'icon' => 'bi-receipt', 'match' => 'expenseCalculations.*'],
                ['route' => 'handCashes.index', 'label' => 'Cash & Accounts', 'icon' => 'bi-wallet2', 'match' => 'handCashes*'],
                ['route' => 'recurring.index', 'label' => 'Recurring', 'icon' => 'bi-arrow-repeat', 'match' => 'recurring.*'],
                ['route' => 'categories.index', 'label' => 'Categories & Budgets', 'icon' => 'bi-tags', 'match' => 'categories.*'],
            ],
        ],
        [
            'label' => 'Reports',
            'can' => 'Admin',
            'items' => [
                ['route' => 'financial_analysis.index', 'label' => 'Financial Analysis', 'icon' => 'bi-clipboard-data'],
                ['route' => 'Budge_Projection', 'label' => 'Budget Projection', 'icon' => 'bi-graph-up-arrow'],
                ['route' => 'Yearly_report', 'label' => 'Yearly Report', 'icon' => 'bi-calendar3'],
                ['route' => 'Monthly_report', 'label' => 'Monthly Report', 'icon' => 'bi-calendar-month'],
                ['route' => 'Monthly_invest', 'label' => 'Monthly Investment', 'icon' => 'bi-piggy-bank'],
                ['route' => 'interactive.dashboard', 'label' => 'Interactive Dashboard', 'icon' => 'bi-speedometer2'],
                ['route' => 'cost_optimisation', 'label' => 'Cost Optimisation', 'icon' => 'bi-scissors'],
                ['route' => 'predictive_budget', 'label' => 'Predictive Budget', 'icon' => 'bi-magic'],
                ['route' => 'power_bi_report', 'label' => 'BI Report', 'icon' => 'bi-bar-chart-line'],
            ],
        ],
        [
            'label' => 'Administration',
            'can' => 'Admin',
            'items' => [
                ['route' => 'users.index', 'label' => 'Users', 'icon' => 'bi-people', 'match' => 'users.*'],
                ['route' => 'roles.index', 'label' => 'Roles', 'icon' => 'bi-person-badge', 'match' => 'roles.*'],
                ['route' => 'online_user', 'label' => 'Online Users', 'icon' => 'bi-person-check'],
            ],
        ],
    ],

    // Mobile bottom bar: four links around a centre "+" quick-add button.
    'bottom' => [
        ['route' => 'home', 'label' => 'Home', 'icon' => 'bi-house-door'],
        ['route' => 'expenseCalculations.index', 'label' => 'Expenses', 'icon' => 'bi-receipt', 'match' => 'expenseCalculations.*'],
        ['route' => 'handCashes.index', 'label' => 'Cash', 'icon' => 'bi-wallet2', 'match' => 'handCashes*'],
        ['route' => 'financial_analysis.index', 'label' => 'Reports', 'icon' => 'bi-bar-chart', 'match' => 'financial_analysis.*'],
    ],

    // "+" quick-add sheet (mobile) and "New" menu (desktop).
    'quick_add' => [
        // ?open=<modal id> is picked up by app.js and opens that modal on load.
        ['route' => 'expenseCalculations.index', 'params' => ['open' => 'CashEntryModal'], 'label' => 'Add income / expense', 'icon' => 'bi-receipt'],
        ['route' => 'handCashes.create', 'label' => 'Add cash entry', 'icon' => 'bi-wallet2'],
        ['route' => 'handCashes_transfer_create', 'label' => 'Transfer between accounts', 'icon' => 'bi-arrow-left-right'],
        ['route' => 'recurring.index', 'label' => 'New recurring transaction', 'icon' => 'bi-arrow-repeat'],
        ['route' => 'categories.create', 'label' => 'New category / budget', 'icon' => 'bi-tag'],
    ],
];
