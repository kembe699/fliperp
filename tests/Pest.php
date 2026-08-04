<?php

use App\Models\Branch;
use App\Models\Category;
use App\Models\ChartOfAccount;
use App\Models\Company;
use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\Department;
use App\Models\Employee;
use App\Models\GoodsReceivedNote;
use App\Models\Invoice;
use App\Models\PaymentType;
use App\Models\PayrollRun;
use App\Models\Position;
use App\Models\PriceList;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\RestaurantTable;
use App\Models\SalaryStructure;
use App\Models\Sale;
use App\Models\StatutoryDeductionRule;
use App\Models\Supplier;
use App\Models\SupplierBill;
use App\Models\SupplierPayment;
use App\Models\TaxRate;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Hr\PayrollRunService;
use App\Services\Pos\SaleService;
use App\Services\Procurement\GrnService;
use App\Services\Procurement\PurchaseOrderService;
use App\Services\Procurement\SupplierPaymentService;
use App\Services\Sales\CustomerPaymentService;
use App\Services\Sales\InvoiceService;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->beforeEach(function () {
        $this->seed(RoleAndPermissionSeeder::class);
        $this->seed(CurrencySeeder::class);
    })
    ->in('Feature');

pest()->extend(TestCase::class)
    ->in('Unit');

function createCompanyWithMainBranch(array $companyAttributes = []): array
{
    $company = Company::factory()->create($companyAttributes);
    $branch = Branch::factory()->create([
        'company_id' => $company->id,
        'code' => 'MAIN',
        'is_main' => true,
    ]);

    return [$company, $branch];
}

function createUserWithRole(string $role, ?Company $company = null, ?Branch $branch = null): User
{
    $user = User::factory()->create([
        'company_id' => $company?->id,
        'branch_id' => $branch?->id,
        'password' => bcrypt('password'),
        'is_active' => true,
    ]);

    $user->assignRole($role);

    return $user;
}

/**
 * A minimal chart of accounts covering the codes JournalEntryService,
 * PayrollRunService and DepreciationService rely on. Returns the created
 * accounts keyed by code.
 *
 * @return array<string, ChartOfAccount>
 */
function seedChartOfAccounts(Company $company): array
{
    $accounts = [
        ['code' => '1000', 'name' => 'Cash and Bank', 'type' => 'asset'],
        ['code' => '1100', 'name' => 'Accounts Receivable', 'type' => 'asset'],
        ['code' => '1200', 'name' => 'Inventory', 'type' => 'asset'],
        ['code' => '1900', 'name' => 'Accumulated Depreciation', 'type' => 'asset'],
        ['code' => '2000', 'name' => 'Accounts Payable', 'type' => 'liability'],
        ['code' => '2100', 'name' => 'Statutory Deductions Payable', 'type' => 'liability'],
        ['code' => '2200', 'name' => 'Net Salaries Payable', 'type' => 'liability'],
        ['code' => '2300', 'name' => 'Tax Payable', 'type' => 'liability'],
        ['code' => '4000', 'name' => 'Sales Revenue', 'type' => 'revenue'],
        ['code' => '5000', 'name' => 'Salaries Expense', 'type' => 'expense'],
        ['code' => '5100', 'name' => 'Depreciation Expense', 'type' => 'expense'],
        ['code' => '5200', 'name' => 'General Operating Expenses', 'type' => 'expense'],
        ['code' => '5300', 'name' => 'Cost of Goods Sold', 'type' => 'expense'],
        ['code' => '5400', 'name' => 'Cash Short/Over', 'type' => 'expense'],
    ];

    $result = [];

    foreach ($accounts as $account) {
        $result[$account['code']] = ChartOfAccount::create([
            'company_id' => $company->id,
            'code' => $account['code'],
            'name' => $account['name'],
            'type' => $account['type'],
            'is_active' => true,
        ]);
    }

    return $result;
}

function createEmployee(Company $company, Branch $branch, array $overrides = []): Employee
{
    $department = Department::create([
        'company_id' => $company->id,
        'branch_id' => $branch->id,
        'name' => $overrides['department_name'] ?? 'Test Department',
    ]);

    $position = Position::create([
        'company_id' => $company->id,
        'department_id' => $department->id,
        'title' => $overrides['position_title'] ?? 'Test Position',
    ]);

    return Employee::create([
        'company_id' => $company->id,
        'branch_id' => $branch->id,
        'department_id' => $department->id,
        'position_id' => $position->id,
        'employee_code' => $overrides['employee_code'] ?? 'EMP-'.Str::upper(Str::random(6)),
        'first_name' => $overrides['first_name'] ?? 'Test',
        'last_name' => $overrides['last_name'] ?? 'Employee',
        'email' => $overrides['email'] ?? null,
        'hire_date' => now()->subYear()->toDateString(),
        'employment_type' => 'full_time',
        'status' => 'active',
    ]);
}

function createCategory(Company $company, array $overrides = []): Category
{
    return Category::create(array_merge([
        'company_id' => $company->id,
        'name' => 'Test Category',
        'is_active' => true,
        'sort_order' => 0,
    ], $overrides));
}

function createUnitOfMeasure(Company $company, array $overrides = []): UnitOfMeasure
{
    return UnitOfMeasure::create(array_merge([
        'company_id' => $company->id,
        'name' => 'Piece',
        'abbreviation' => 'PC-'.Str::upper(Str::random(4)),
    ], $overrides));
}

function createProduct(Company $company, array $overrides = []): Product
{
    $category = $overrides['category'] ?? createCategory($company);
    $unit = $overrides['unit'] ?? createUnitOfMeasure($company);

    return Product::create([
        'company_id' => $company->id,
        'category_id' => $category->id,
        'unit_of_measure_id' => $unit->id,
        'name' => $overrides['name'] ?? 'Test Product',
        'sku' => $overrides['sku'] ?? 'SKU-'.Str::upper(Str::random(8)),
        'cost_price' => $overrides['cost_price'] ?? 10,
        'selling_price' => $overrides['selling_price'] ?? 20,
        'reorder_level' => $overrides['reorder_level'] ?? 10,
        'is_active' => true,
        'track_inventory' => $overrides['track_inventory'] ?? true,
    ]);
}

function createWarehouse(Company $company, Branch $branch, array $overrides = []): Warehouse
{
    return Warehouse::create(array_merge([
        'company_id' => $company->id,
        'branch_id' => $branch->id,
        'name' => 'Test Warehouse',
        'code' => 'WH-'.Str::upper(Str::random(6)),
        'is_default' => false,
    ], $overrides));
}

function createSupplier(Company $company, array $overrides = []): Supplier
{
    return Supplier::create(array_merge([
        'company_id' => $company->id,
        'name' => 'Test Supplier',
        'payment_terms_days' => 30,
        'is_active' => true,
    ], $overrides));
}

function createTaxRate(Company $company, array $overrides = []): TaxRate
{
    return TaxRate::create(array_merge([
        'company_id' => $company->id,
        'name' => 'VAT',
        'rate' => 18,
        'is_default' => true,
        'is_active' => true,
    ], $overrides));
}

function createPaymentType(Company $company, array $overrides = []): PaymentType
{
    return PaymentType::create(array_merge([
        'company_id' => $company->id,
        'name' => 'Cash',
        'type' => 'cash',
        'is_active' => true,
    ], $overrides));
}

function createCustomer(Company $company, array $overrides = []): Customer
{
    return Customer::create(array_merge([
        'company_id' => $company->id,
        'name' => 'Test Customer',
        'phone' => '+10000000000',
        'customer_type' => 'walk_in',
        'credit_limit' => 0,
        'is_active' => true,
    ], $overrides));
}

function createRestaurantTable(Company $company, Branch $branch, array $overrides = []): RestaurantTable
{
    return RestaurantTable::create(array_merge([
        'company_id' => $company->id,
        'branch_id' => $branch->id,
        'name' => 'Table 1',
        'capacity' => 4,
        'status' => 'available',
    ], $overrides));
}

function createPriceList(Company $company, array $overrides = []): PriceList
{
    return PriceList::create(array_merge([
        'company_id' => $company->id,
        'name' => 'Standard Price List',
        'currency_code' => 'USD',
        'is_default' => false,
        'is_active' => true,
    ], $overrides));
}

function createPromotion(Company $company, array $overrides = []): Promotion
{
    return Promotion::create(array_merge([
        'company_id' => $company->id,
        'name' => 'Test Promotion',
        'type' => 'percentage_discount',
        'value' => 10,
        'applies_to' => 'all_products',
        'start_date' => now()->subDay()->toDateString(),
        'end_date' => now()->addDay()->toDateString(),
        'is_active' => true,
    ], $overrides));
}

/**
 * Builds one deterministic multi-module scenario (purchase, POS sale,
 * invoice + payment, supplier payment, payroll run) that every report
 * service can be asserted against with exact expected numbers. All dates
 * are fixed within January 2026 so results never depend on "today".
 *
 * The caller must have already called Sanctum::actingAs($actor) — the
 * underlying services rely on Auth for company scoping and actor fields.
 *
 * Journal entries produced (all posted, entry_date in parentheses):
 *   1. GRN confirm      (2026-01-05) dr Inventory 5000 / cr AP 5000
 *   2. POS sale complete(2026-01-10) dr Cash 2200, dr COGS 1000 / cr Revenue 2000, cr TaxPayable 200, cr Inventory 1000
 *   3. Invoice send     (2026-01-12) dr AR 1100 / cr Revenue 1000, cr TaxPayable 100
 *   4. Supplier payment (2026-01-15) dr AP 3000 / cr Cash 3000
 *   5. Customer payment (2026-01-20) dr Cash 1100 / cr AR 1100
 *   6. Payroll process  (2026-01-31) dr SalaryExpense 1000 / cr StatutoryPayable 100, cr NetSalariesPayable 900
 *
 * Resulting balances (2026-01-01..2026-01-31): Cash 300, Inventory 4000,
 * AR 0, AP 2000, StatutoryPayable 100, NetSalariesPayable 900, TaxPayable 300,
 * Revenue 3000, SalaryExpense 1000, COGS 1000. Total debit = total credit = 14400.
 * Gross profit = 3000 - 1000 = 2000. Net profit = 2000 - 1000 = 1000.
 * Assets 4300 = Liabilities 3300 + Equity(net income) 1000.
 *
 * @return array{warehouse:Warehouse,product:Product,taxRate:TaxRate,cash:PaymentType,supplier:Supplier,customer:Customer,grn:GoodsReceivedNote,bill:SupplierBill,sale:Sale,invoice:Invoice,supplierPayment:SupplierPayment,customerPayment:CustomerPayment,employee:Employee,payrollRun:PayrollRun,from:string,to:string}
 */
function seedReportingFixture(Company $company, Branch $branch): array
{
    $warehouse = createWarehouse($company, $branch);
    $product = createProduct($company, ['cost_price' => 100, 'selling_price' => 200]);
    $taxRate = createTaxRate($company, ['rate' => 10]);
    $cash = createPaymentType($company, ['name' => 'Cash', 'type' => 'cash']);
    $supplier = createSupplier($company);
    $customer = createCustomer($company, ['customer_type' => 'regular']);

    $purchaseOrderService = app(PurchaseOrderService::class);
    $grnService = app(GrnService::class);
    $supplierPaymentService = app(SupplierPaymentService::class);
    $saleService = app(SaleService::class);
    $invoiceService = app(InvoiceService::class);
    $customerPaymentService = app(CustomerPaymentService::class);
    $payrollRunService = app(PayrollRunService::class);

    $po = $purchaseOrderService->create([
        'branch_id' => $branch->id,
        'warehouse_id' => $warehouse->id,
        'supplier_id' => $supplier->id,
        'reference_number' => 'RPT-PO-0001',
        'order_date' => '2026-01-01',
        'items' => [
            ['product_id' => $product->id, 'quantity_ordered' => 50, 'unit_cost' => 100],
        ],
    ]);
    $purchaseOrderService->submit($po);
    $po = $purchaseOrderService->approve($po);

    $grn = $grnService->create([
        'purchase_order_id' => $po->id,
        'warehouse_id' => $warehouse->id,
        'supplier_id' => $supplier->id,
        'reference_number' => 'RPT-GRN-0001',
        'received_date' => '2026-01-05',
        'items' => [
            ['product_id' => $product->id, 'purchase_order_item_id' => $po->items->first()->id, 'quantity_received' => 50, 'unit_cost' => 100, 'condition' => 'good'],
        ],
    ]);
    $grn = $grnService->confirm($grn);
    $bill = SupplierBill::where('grn_id', $grn->id)->firstOrFail();

    $sale = $saleService->create([
        'branch_id' => $branch->id,
        'warehouse_id' => $warehouse->id,
        'customer_id' => $customer->id,
        'reference_number' => 'RPT-SALE-0001',
        'sale_type' => 'pos',
        'sale_date' => '2026-01-10',
        'items' => [
            ['product_id' => $product->id, 'quantity' => 10, 'unit_price' => 200, 'tax_rate_id' => $taxRate->id],
        ],
    ]);
    $saleService->addPayment($sale, ['payment_type_id' => $cash->id, 'amount' => 2200, 'paid_at' => '2026-01-10']);
    $sale = $saleService->complete($sale->fresh());

    $invoice = $invoiceService->create([
        'branch_id' => $branch->id,
        'customer_id' => $customer->id,
        'reference_number' => 'RPT-INV-0001',
        'invoice_date' => '2026-01-12',
        'due_date' => '2026-02-11',
        'items' => [
            ['product_id' => $product->id, 'quantity' => 5, 'unit_price' => 200, 'tax_rate_id' => $taxRate->id],
        ],
    ]);
    $invoice = $invoiceService->send($invoice);

    $supplierPayment = $supplierPaymentService->create([
        'supplier_id' => $supplier->id,
        'supplier_bill_id' => $bill->id,
        'payment_date' => '2026-01-15',
        'amount' => 3000,
        'payment_type_id' => $cash->id,
    ]);

    $customerPayment = $customerPaymentService->create([
        'customer_id' => $customer->id,
        'invoice_id' => $invoice->id,
        'payment_type_id' => $cash->id,
        'payment_date' => '2026-01-20',
        'amount' => 1100,
    ]);

    $employee = createEmployee($company, $branch, ['employee_code' => 'RPT-EMP-001']);
    SalaryStructure::create([
        'employee_id' => $employee->id,
        'basic_salary' => 1000,
        'allowances' => [],
        'effective_date' => '2026-01-01',
    ]);
    StatutoryDeductionRule::create([
        'company_id' => $company->id,
        'name' => 'Fixed Tax',
        'type' => 'tax',
        'calculation_type' => 'fixed',
        'config' => ['amount' => 100],
        'country_code' => 'UGA',
        'is_active' => true,
    ]);
    $payrollRun = $payrollRunService->create([
        'branch_id' => $branch->id,
        'period_start' => '2026-01-01',
        'period_end' => '2026-01-31',
    ]);
    $payrollRun = $payrollRunService->process($payrollRun);

    return [
        'warehouse' => $warehouse,
        'product' => $product,
        'taxRate' => $taxRate,
        'cash' => $cash,
        'supplier' => $supplier,
        'customer' => $customer,
        'grn' => $grn,
        'bill' => $bill->fresh(),
        'sale' => $sale->fresh(),
        'invoice' => $invoice->fresh(),
        'supplierPayment' => $supplierPayment,
        'customerPayment' => $customerPayment,
        'employee' => $employee,
        'payrollRun' => $payrollRun->fresh(),
        'from' => '2026-01-01',
        'to' => '2026-01-31',
    ];
}
