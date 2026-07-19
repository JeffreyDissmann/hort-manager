<?php

declare(strict_types=1);

// Buchhaltung (admin-only accounting module) — page headers, labels and hints.
return [
    'title' => 'Accounting',

    'accounts' => [
        'title' => 'Accounts',
        'intro' => 'Bank and cash accounts. The balance is the opening balance plus every confirmed booking.',
        'new' => 'New account',
        'edit' => 'Edit account',
        'name' => 'Name',
        'name_placeholder' => 'e.g. Bank, Cash box',
        'iban' => 'IBAN',
        'iban_invalid' => 'Please enter a valid IBAN (e.g. DE89 3704 0044 0532 0130 00).',
        'opening_balance' => 'Opening balance (€)',
        'opening_balance_date' => 'Opening balance date',
        'active' => 'Active',
        'active_hint' => 'Inactive accounts are kept but no longer offered when booking.',
        'balance' => 'Balance',
        'bookings_count' => 'Bookings',
        'empty' => 'No accounts yet.',
        'delete_confirm' => 'Really delete account “:name”?',
    ],

    'categories' => [
        'title' => 'Categories',
        'intro' => 'Booking categories as a tree. The direction (income/expense) is set at the top level and inherited by every subcategory.',
        'income' => 'Income',
        'expense' => 'Expenses',
        'new_root' => 'New top-level category',
        'add_child' => 'Subcategory',
        'name_placeholder' => 'Category name',
        'active' => 'Active',
        'inactive' => 'Inactive',
        'bookings_count' => 'Bookings',
        'empty' => 'No categories yet.',
        'delete_confirm' => 'Really delete category “:name”?',
        'rename' => 'Rename',
        'deactivate' => 'Deactivate',
        'activate' => 'Activate',
    ],
];
