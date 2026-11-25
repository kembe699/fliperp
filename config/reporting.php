<?php

/*
 * Defaults for each company reporting mode (companies.reporting_mode). A
 * company can override any of them in companies.reporting_options.
 */
return [
    'modes' => ['standard', 'nonprofit'],

    'standard' => [
        'show_cost_of_sales' => true,
    ],

    'nonprofit' => [
        // A not-for-profit has no cost of goods sold or gross profit: every
        // expense account, cost-of-sales mapping included, is expenditure.
        'show_cost_of_sales' => false,
    ],
];
