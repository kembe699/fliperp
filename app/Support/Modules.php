<?php

namespace App\Support;

/**
 * The modules a client company can be given. Platform staff switch them on
 * and off per client (companies.enabled_modules); a company with the column
 * null has every module, which is what every company had before modules
 * existed.
 *
 * A module is enforced through permissions: each owns permission groups (the
 * part of "grants.view" before the dot), and a group is denied to everyone in
 * a company when every module owning it is off (see User::hasPermissionTo).
 * Policies, routes and the sidebar all already check those permissions, so a
 * switched-off module disappears from the screens and its API refuses access.
 *
 * Some groups are shared: products, categories and warehouses serve POS,
 * Inventory and Procurement alike, so they stay available while any of those
 * is on. Groups owned by no module (users, roles, branches, tax rates,
 * payment types, the dashboard, support tickets) are always available.
 */
class Modules
{
    protected const CATALOGUE_ITEMS = ['categories', 'units-of-measure', 'products', 'warehouses', 'stock-levels', 'departments'];

    /** @var array<string, array{label: string, description: string, groups: array<int, string>}> */
    public const ALL = [
        'sales' => [
            'label' => 'POS & Sales',
            'description' => 'Point of sale, receipts, invoices, quotations, customers, price lists and promotions.',
            'groups' => ['sales', 'cash-drawer-sessions', 'pos-reports', 'restaurant-tables', 'customers', 'customer-payments', 'invoices', 'quotations', 'price-lists', 'promotions', ...self::CATALOGUE_ITEMS],
        ],
        'inventory' => [
            'label' => 'Inventory',
            'description' => 'Products, categories, units, warehouses, stock levels, transfers and adjustments.',
            'groups' => ['stock-movements', 'stock-transfers', 'stock-adjustments', ...self::CATALOGUE_ITEMS],
        ],
        'procurement' => [
            'label' => 'Procurement',
            'description' => 'Suppliers, purchase orders, goods received notes, supplier bills and payments.',
            'groups' => ['suppliers', 'purchase-orders', 'goods-received-notes', 'supplier-bills', 'supplier-payments', ...self::CATALOGUE_ITEMS],
        ],
        'hr' => [
            'label' => 'HR & Payroll',
            'description' => 'Departments, employees, contracts, attendance, leave and payroll.',
            'groups' => ['departments', 'positions', 'employees', 'employee-contracts', 'attendance', 'attendance-geofences', 'leave-types', 'leave-requests', 'payroll-runs', 'salary-structures', 'statutory-deduction-rules'],
        ],
        'assets' => [
            'label' => 'Assets',
            'description' => 'Fixed asset register, assignment, disposal and depreciation.',
            'groups' => ['asset-categories', 'assets'],
        ],
        'logistics' => [
            'label' => 'Logistics',
            'description' => 'Vehicles and dispatch tracking.',
            'groups' => ['vehicles', 'dispatches'],
        ],
        'crm' => [
            'label' => 'CRM',
            'description' => 'Leads, deals pipeline, customer services, activities, meetings and CRM reports.',
            'groups' => ['crm-services', 'crm-pipeline-stages', 'crm-leads', 'crm-deals', 'crm-customer-services', 'crm-account-assignments', 'crm-activities', 'crm-meetings', 'crm-emails', 'crm-reports', 'crm-customers'],
        ],
        'budgeting' => [
            'label' => 'Budgeting',
            'description' => 'Budget periods, budget lines and budget vs actual.',
            'groups' => ['budget-periods', 'budget-lines'],
        ],
        'me' => [
            'label' => 'M&E',
            'description' => 'Monitoring & evaluation projects, indicators, activities and results.',
            'groups' => ['me-projects', 'me-indicators', 'me-activities', 'me-results'],
        ],
        'grants' => [
            'label' => 'Grants',
            'description' => 'SOFs (grants), DEAs, donors, cost centres and the grant report.',
            'groups' => ['grants', 'donors', 'cost-centres'],
        ],
        'accounting' => [
            'label' => 'Accounting',
            'description' => 'Chart of accounts, journal entries, periods, exchange rates and the financial reports.',
            'groups' => ['chart-of-accounts', 'journal-entries', 'exchange-rates', 'accounting-periods', 'reports'],
        ],
    ];

    /** @return array<int, string> */
    public static function keys(): array
    {
        return array_keys(self::ALL);
    }

    /**
     * The modules a company has: its own list, or every module when it has
     * none set (companies from before modules existed).
     *
     * @return array<int, string>
     */
    public static function enabledFor(?array $enabled): array
    {
        return $enabled === null ? self::keys() : array_values(array_intersect(self::keys(), $enabled));
    }

    /**
     * Permission groups switched off for a company with $enabled modules:
     * owned by at least one module, and by no module that is on.
     *
     * @return array<int, string>
     */
    public static function disabledGroups(?array $enabled): array
    {
        if ($enabled === null) {
            return [];
        }

        $on = self::enabledFor($enabled);
        $allowed = collect(self::ALL)->only($on)->pluck('groups')->flatten()->unique();

        return collect(self::ALL)->pluck('groups')->flatten()->unique()->diff($allowed)->values()->all();
    }

    /** @return array<int, array{key: string, label: string, description: string}> */
    public static function catalogue(): array
    {
        return collect(self::ALL)->map(fn (array $module, string $key) => [
            'key' => $key,
            'label' => $module['label'],
            'description' => $module['description'],
        ])->values()->all();
    }
}
