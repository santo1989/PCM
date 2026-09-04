<?php

/**
 * Centralized enumerated option lists for the finance entry forms.
 *
 * These were previously hardcoded <select> option lists duplicated (and drifting)
 * across categories/{create,edit}.blade.php, expenseCalculations/index.blade.php,
 * and handCashes/{create,edit,transfer}.blade.php. Values are always uppercase to
 * match the uppercase-on-save mutators on the Category/ExpenseCalculation/HandCash
 * models, so any strict/case-sensitive comparison against a stored value works
 * without extra normalization.
 */

return [

    // Category "types" — top-level classification, also used by ExpenseCalculation.
    'category_types' => [
        'INCOME' => 'Income',
        'EXPENSE' => 'Expense',
        'LOAN' => 'Loan to Other',
        'RETURN' => 'Loan Return',
    ],

    // The 50/30/20 budgeting bucket a category/expense line belongs to.
    'budget_rules' => [
        'NEEDS' => '50% of income: needs',
        'WANTS' => '30% of income: wants',
        'SAVINGS' => '20% of income: savings',
    ],

    // HandCash "types" — whether a hand-cash row is a deposit or a withdrawal.
    'handcash_types' => [
        'SAVE' => 'Savings',
        'WIDROWS' => 'Withdraws',
    ],

    // HandCash "rules" — which account/bucket a hand-cash row belongs to.
    'handcash_rules' => [
        'PETI' => 'Peti Cash',
        'CASH' => 'Cash',
        'CITY_BANK' => 'City Bank',
        'CITY_BANK_ISLAMIC' => 'City Bank Islamic',
        'SONALI_BANK_GULSHAN' => 'Sonali Bank Gulshan',
        'SONALI_BANK_TONGI' => 'Sonali Bank Tongi',
        'DBBL' => 'Dutch Bangla Bank',
        'PBL' => 'Prime Bank Ltd',
        'FD' => 'FD',
        'DPS' => 'DPS',
        'ISLAMIC_DPS' => 'Islamic DPS',
        'MYLOAN' => 'MyLoan',
        'DPSLOAN' => 'DPS Loan',
        'LOAN' => 'Loan To Other',
        'CREDITCARD' => 'Credit Card',
        'MOBILE_BKASH' => 'Bkash',
        'MOBILE_ROCKET' => 'Rocket',
        'MOBILE_NAGAD' => 'Nagad',
        'INVESTMENT' => 'Investment',
    ],

];
