<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleAndPermissionSeeder extends Seeder
{
    /**
     * Every permission is "{module}.{action}". Add a module here and it is
     * automatically created; grant it to roles via $rolePermissions below.
     */
    protected array $modules = [
        'companies' => ['view', 'create', 'update', 'delete'],
        'branches' => ['view', 'create', 'update', 'delete'],
        'users' => ['view', 'create', 'update', 'delete'],
        'roles' => ['view', 'create', 'update', 'delete'],
        'permissions' => ['view', 'assign'],
        'audit-logs' => ['view'],

        // Finance core
        'chart-of-accounts' => ['view', 'create', 'update', 'delete'],
        'journal-entries' => ['view', 'create', 'update', 'delete', 'post', 'reverse'],

        // HR & Payroll
        'departments' => ['view', 'create', 'update', 'delete'],
        'positions' => ['view', 'create', 'update', 'delete'],
        'employees' => ['view', 'create', 'update', 'delete'],
        'employee-contracts' => ['view', 'create', 'update', 'delete'],
        'attendance' => ['view', 'create', 'update', 'delete'],
        'attendance-geofences' => ['view', 'create', 'update', 'delete'],
        'leave-types' => ['view', 'create', 'update', 'delete'],
        'leave-requests' => ['view', 'create', 'update', 'delete', 'approve', 'reject'],
        'payroll-runs' => ['view', 'create', 'process'],
        'salary-structures' => ['view', 'create', 'update', 'delete'],
        'statutory-deduction-rules' => ['view', 'create', 'update', 'delete'],

        // Assets
        'asset-categories' => ['view', 'create', 'update', 'delete'],
        'assets' => ['view', 'create', 'update', 'delete', 'assign', 'dispose', 'run-depreciation'],

        // Logistics
        'vehicles' => ['view', 'create', 'update', 'delete'],
        'dispatches' => ['view', 'create', 'update', 'delete', 'track'],

        // Budgeting
        'budget-periods' => ['view', 'create', 'update', 'delete'],
        'budget-lines' => ['view', 'create', 'update', 'delete'],

        // M&E
        'me-projects' => ['view', 'create', 'update', 'delete'],
        'me-indicators' => ['view', 'create', 'update', 'delete'],
        'me-activities' => ['view', 'create', 'update', 'delete'],
        'me-results' => ['view', 'create', 'update', 'delete'],

        // Products & Inventory
        'categories' => ['view', 'create', 'update', 'delete'],
        'units-of-measure' => ['view', 'create', 'update', 'delete'],
        'products' => ['view', 'create', 'update', 'delete'],
        'warehouses' => ['view', 'create', 'update', 'delete'],
        'stock-levels' => ['view'],
        'stock-movements' => ['view', 'manual'],
        'stock-transfers' => ['view', 'create', 'update', 'delete', 'complete'],
        'stock-adjustments' => ['view', 'create', 'update', 'delete', 'approve'],

        // Procurement & GRN
        'suppliers' => ['view', 'create', 'update', 'delete'],
        'purchase-orders' => ['view', 'create', 'update', 'delete', 'submit', 'approve', 'cancel'],
        'goods-received-notes' => ['view', 'create', 'update', 'delete', 'confirm'],
        'supplier-bills' => ['view', 'create', 'update', 'delete'],
        'supplier-payments' => ['view', 'create', 'update', 'delete'],

        // POS
        'tax-rates' => ['view', 'create', 'update', 'delete'],
        'payment-types' => ['view', 'create', 'update', 'delete'],
        'customers' => ['view', 'create', 'update', 'delete'],
        'restaurant-tables' => ['view', 'create', 'update', 'delete'],
        'cash-drawer-sessions' => ['view', 'open', 'close', 'reconcile'],
        'sales' => ['view', 'create', 'update', 'delete', 'hold', 'complete', 'void', 'refund'],
        'pos-reports' => ['view'],

        // Sales: quotations, invoices, price lists, promotions
        'price-lists' => ['view', 'create', 'update', 'delete'],
        'promotions' => ['view', 'create', 'update', 'delete'],
        'quotations' => ['view', 'create', 'update', 'delete', 'send', 'accept', 'reject', 'convert-to-invoice'],
        'invoices' => ['view', 'create', 'update', 'delete', 'send', 'cancel'],
        'customer-payments' => ['view', 'create', 'update', 'delete'],

        // Accounting Reports & Dashboard
        'accounting-periods' => ['view', 'create', 'update', 'delete', 'close'],
        'reports' => ['view', 'snapshot'],
        'dashboard' => ['view'],
    ];

    /**
     * super_admin implicitly gets every permission (see run()). Every other
     * role lists the modules/actions it is granted.
     *
     * IMPORTANT: Spatie's roles/permissions tables have no company_id — a
     * role like "cashier" is one shared row used by every company in this
     * database, not a per-tenant copy (Spatie's "teams" feature, which
     * would fix this properly, is present in the migration but disabled —
     * see config/permission.php). Granting company_admin roles.create/
     * update/delete means a company_admin can rename, re-permission, or
     * delete a role that other companies' users also hold. This is an
     * accepted, flagged tradeoff for now so the in-app role management UI
     * is actually usable; proper isolation needs Spatie teams enabled plus
     * a data migration to split existing shared role rows per company.
     */
    protected array $rolePermissions = [
        'company_admin' => [
            'companies' => ['view', 'update'],
            'branches' => ['view', 'create', 'update', 'delete'],
            'users' => ['view', 'create', 'update', 'delete'],
            'roles' => ['view', 'create', 'update', 'delete'],
            'permissions' => ['view', 'assign'],
            'audit-logs' => ['view'],
            'chart-of-accounts' => ['view', 'create', 'update', 'delete'],
            'journal-entries' => ['view', 'create', 'update', 'delete', 'post', 'reverse'],
            'departments' => ['view', 'create', 'update', 'delete'],
            'positions' => ['view', 'create', 'update', 'delete'],
            'employees' => ['view', 'create', 'update', 'delete'],
            'employee-contracts' => ['view', 'create', 'update', 'delete'],
            'attendance' => ['view', 'create', 'update', 'delete'],
            'attendance-geofences' => ['view', 'create', 'update', 'delete'],
            'leave-types' => ['view', 'create', 'update', 'delete'],
            'leave-requests' => ['view', 'create', 'update', 'delete', 'approve', 'reject'],
            'payroll-runs' => ['view', 'create', 'process'],
            'salary-structures' => ['view', 'create', 'update', 'delete'],
            'statutory-deduction-rules' => ['view', 'create', 'update', 'delete'],
            'asset-categories' => ['view', 'create', 'update', 'delete'],
            'assets' => ['view', 'create', 'update', 'delete', 'assign', 'dispose', 'run-depreciation'],
            'vehicles' => ['view', 'create', 'update', 'delete'],
            'dispatches' => ['view', 'create', 'update', 'delete', 'track'],
            'budget-periods' => ['view', 'create', 'update', 'delete'],
            'budget-lines' => ['view', 'create', 'update', 'delete'],
            'me-projects' => ['view', 'create', 'update', 'delete'],
            'me-indicators' => ['view', 'create', 'update', 'delete'],
            'me-activities' => ['view', 'create', 'update', 'delete'],
            'me-results' => ['view', 'create', 'update', 'delete'],
            'categories' => ['view', 'create', 'update', 'delete'],
            'units-of-measure' => ['view', 'create', 'update', 'delete'],
            'products' => ['view', 'create', 'update', 'delete'],
            'warehouses' => ['view', 'create', 'update', 'delete'],
            'stock-levels' => ['view'],
            'stock-movements' => ['view', 'manual'],
            'stock-transfers' => ['view', 'create', 'update', 'delete', 'complete'],
            'stock-adjustments' => ['view', 'create', 'update', 'delete', 'approve'],
            'suppliers' => ['view', 'create', 'update', 'delete'],
            'purchase-orders' => ['view', 'create', 'update', 'delete', 'submit', 'approve', 'cancel'],
            'goods-received-notes' => ['view', 'create', 'update', 'delete', 'confirm'],
            'supplier-bills' => ['view', 'create', 'update', 'delete'],
            'supplier-payments' => ['view', 'create', 'update', 'delete'],
            'tax-rates' => ['view', 'create', 'update', 'delete'],
            'payment-types' => ['view', 'create', 'update', 'delete'],
            'customers' => ['view', 'create', 'update', 'delete'],
            'restaurant-tables' => ['view', 'create', 'update', 'delete'],
            'cash-drawer-sessions' => ['view', 'open', 'close', 'reconcile'],
            'sales' => ['view', 'create', 'update', 'delete', 'hold', 'complete', 'void', 'refund'],
            'pos-reports' => ['view'],
            'price-lists' => ['view', 'create', 'update', 'delete'],
            'promotions' => ['view', 'create', 'update', 'delete'],
            'quotations' => ['view', 'create', 'update', 'delete', 'send', 'accept', 'reject', 'convert-to-invoice'],
            'invoices' => ['view', 'create', 'update', 'delete', 'send', 'cancel'],
            'customer-payments' => ['view', 'create', 'update', 'delete'],
            'accounting-periods' => ['view', 'create', 'update', 'delete', 'close'],
            'reports' => ['view', 'snapshot'],
            'dashboard' => ['view'],
        ],
        'branch_manager' => [
            'branches' => ['view'],
            'users' => ['view', 'create', 'update'],
            'audit-logs' => ['view'],
            'departments' => ['view'],
            'positions' => ['view'],
            'employees' => ['view', 'create', 'update'],
            'employee-contracts' => ['view'],
            'attendance' => ['view', 'create', 'update'],
            'attendance-geofences' => ['view', 'create', 'update'],
            'leave-types' => ['view'],
            'leave-requests' => ['view', 'create', 'approve', 'reject'],
            'assets' => ['view'],
            'vehicles' => ['view'],
            'dispatches' => ['view'],
            'categories' => ['view'],
            'products' => ['view'],
            'warehouses' => ['view'],
            'stock-levels' => ['view'],
            'stock-movements' => ['view'],
            'stock-transfers' => ['view'],
            'stock-adjustments' => ['view', 'create', 'approve'],
            'suppliers' => ['view'],
            'purchase-orders' => ['view'],
            'goods-received-notes' => ['view'],
            'tax-rates' => ['view'],
            'payment-types' => ['view'],
            'customers' => ['view', 'create', 'update'],
            'restaurant-tables' => ['view', 'create', 'update'],
            'cash-drawer-sessions' => ['view', 'open', 'close', 'reconcile'],
            'sales' => ['view', 'create', 'update', 'delete', 'hold', 'complete', 'void', 'refund'],
            'pos-reports' => ['view'],
            'price-lists' => ['view'],
            'promotions' => ['view'],
            'quotations' => ['view', 'create', 'update', 'send', 'accept', 'reject', 'convert-to-invoice'],
            'invoices' => ['view', 'create', 'update', 'send'],
            'customer-payments' => ['view', 'create'],
            'dashboard' => ['view'],
        ],
        'cashier' => [
            'branches' => ['view'],
            'products' => ['view'],
            'stock-levels' => ['view'],
            'tax-rates' => ['view'],
            'payment-types' => ['view'],
            'customers' => ['view', 'create', 'update'],
            'restaurant-tables' => ['view', 'update'],
            // Deliberately no reconcile: a cashier shouldn't be able to mark
            // their own shortage as recovered without a supervisor/accountant
            // involved — that's exactly the control this action exists for.
            'cash-drawer-sessions' => ['view', 'open', 'close'],
            'sales' => ['view', 'create', 'update', 'delete', 'hold', 'complete', 'void', 'refund'],
            'pos-reports' => ['view'],
            'dashboard' => ['view'],
        ],
        'accountant' => [
            'branches' => ['view'],
            'audit-logs' => ['view'],
            'chart-of-accounts' => ['view', 'create', 'update', 'delete'],
            'journal-entries' => ['view', 'create', 'update', 'delete', 'post', 'reverse'],
            'payroll-runs' => ['view', 'process'],
            'salary-structures' => ['view'],
            'statutory-deduction-rules' => ['view', 'create', 'update', 'delete'],
            'assets' => ['view', 'run-depreciation'],
            'asset-categories' => ['view'],
            'budget-periods' => ['view', 'create', 'update', 'delete'],
            'budget-lines' => ['view', 'create', 'update', 'delete'],
            'me-projects' => ['view'],
            'products' => ['view'],
            'stock-levels' => ['view'],
            'suppliers' => ['view'],
            'purchase-orders' => ['view'],
            'goods-received-notes' => ['view'],
            'supplier-bills' => ['view', 'create', 'update', 'delete'],
            'supplier-payments' => ['view', 'create', 'update', 'delete'],
            'tax-rates' => ['view'],
            'payment-types' => ['view'],
            'customers' => ['view'],
            'cash-drawer-sessions' => ['view', 'reconcile'],
            'sales' => ['view'],
            'pos-reports' => ['view'],
            'price-lists' => ['view'],
            'promotions' => ['view'],
            'quotations' => ['view'],
            'invoices' => ['view', 'create', 'update', 'send', 'cancel'],
            'customer-payments' => ['view', 'create', 'update', 'delete'],
            'accounting-periods' => ['view', 'create', 'update', 'delete', 'close'],
            'reports' => ['view', 'snapshot'],
            'dashboard' => ['view'],
        ],
        'procurement_officer' => [
            'branches' => ['view'],
            'vehicles' => ['view', 'create', 'update', 'delete'],
            'dispatches' => ['view', 'create', 'update', 'delete', 'track'],
            'assets' => ['view'],
            'asset-categories' => ['view'],
            'categories' => ['view', 'create', 'update', 'delete'],
            'units-of-measure' => ['view', 'create', 'update', 'delete'],
            'products' => ['view', 'create', 'update', 'delete'],
            'warehouses' => ['view', 'create', 'update', 'delete'],
            'stock-levels' => ['view'],
            'stock-movements' => ['view'],
            'stock-transfers' => ['view', 'create', 'update', 'delete', 'complete'],
            'stock-adjustments' => ['view', 'create'],
            'suppliers' => ['view', 'create', 'update', 'delete'],
            'purchase-orders' => ['view', 'create', 'update', 'delete', 'submit', 'approve', 'cancel'],
            'goods-received-notes' => ['view', 'create', 'update', 'delete', 'confirm'],
            'supplier-bills' => ['view', 'create'],
            'supplier-payments' => ['view', 'create'],
        ],

        // Employee Self-Service Portal: intentionally granted zero module
        // permissions. Portal endpoints authorize by checking the acting
        // user's own linked employee_id, not Spatie permissions — this role
        // exists only to identify/tag portal-only accounts.
        'employee' => [],
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach ($this->modules as $module => $actions) {
            foreach ($actions as $action) {
                Permission::firstOrCreate(['name' => "{$module}.{$action}", 'guard_name' => 'web']);
            }
        }

        $superAdmin = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $superAdmin->syncPermissions(Permission::all());

        foreach ($this->rolePermissions as $roleName => $modulePermissions) {
            $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);

            $permissions = [];
            foreach ($modulePermissions as $module => $actions) {
                foreach ($actions as $action) {
                    $permissions[] = "{$module}.{$action}";
                }
            }

            $role->syncPermissions($permissions);
        }
    }
}
