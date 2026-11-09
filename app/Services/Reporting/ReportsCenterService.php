<?php

namespace App\Services\Reporting;

use App\Models\JournalEntry;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * The Reports Center: one place for every operational and financial report,
 * each returned in the same shape so the frontend renders them all with one
 * table:
 *
 *   ['columns' => [['key','label','type']...], 'rows' => [...], 'totals' => [...]]
 *
 * type is one of text | money | number | percent | date. Money is always in
 * the company's base currency: documents written in another currency are
 * converted at their own rate (amount / exchange_rate), POS sales are always
 * base. Revenue is net of discount and excludes tax — the same figure the
 * Profit & Loss posts.
 */
class ReportsCenterService
{
    public const REPORTS = [
        'overview', 'revenue-accounts', 'expense-accounts', 'revenue', 'collections',
        'purchases', 'supplier-payments', 'stock', 'stock-movements', 'payroll', 'audit',
    ];

    /** Invoice statuses that count as revenue (raised and not withdrawn). */
    protected const INVOICE_LIVE = ['sent', 'partially_paid', 'paid', 'overdue'];

    public function __construct(protected ProfitAndLossService $profitAndLoss) {}

    /**
     * @param  array{from:string,to:string,branch_id?:int|null,group_by?:string|null,source?:string|null,status?:string|null}  $f
     */
    public function run(string $report, int $companyId, array $f): array
    {
        return match ($report) {
            'overview' => $this->overview($companyId, $f),
            'revenue-accounts' => $this->accounts($companyId, $f, 'revenue'),
            'expense-accounts' => $this->accounts($companyId, $f, 'expense'),
            'revenue' => $this->revenue($companyId, $f),
            'collections' => $this->collections($companyId, $f),
            'purchases' => $this->purchases($companyId, $f),
            'supplier-payments' => $this->supplierPayments($companyId, $f),
            'stock' => $this->stock($companyId, $f),
            'stock-movements' => $this->stockMovements($companyId, $f),
            'payroll' => $this->payroll($companyId, $f),
            'audit' => $this->audit($companyId, $f),
            default => throw new InvalidArgumentException("Unknown report {$report}"),
        };
    }

    // ── Overview ────────────────────────────────────────────────────────────

    protected function overview(int $companyId, array $f): array
    {
        $branchId = $f['branch_id'] ?? null;
        $pl = $this->profitAndLoss->generate($companyId, $f['from'], $f['to'], $branchId);

        $sales = $this->salesQuery($companyId, $f)->selectRaw('COUNT(*) AS n, COALESCE(SUM(sales.subtotal - sales.discount_amount), 0) AS revenue')->first();
        $invoices = $this->invoicesQuery($companyId, $f)->selectRaw('COUNT(*) AS n, COALESCE(SUM((invoices.subtotal - invoices.discount_amount) / '.$this->rate('invoices').'), 0) AS revenue')->first();
        $collected = collect($this->collectionRows($companyId, $f, 'all'))->sum('amount');
        $bills = $this->billsQuery($companyId, $f)->selectRaw('COALESCE(SUM(supplier_bills.total_amount / '.$this->rate('supplier_bills').'), 0) AS total')->value('total');

        $receivable = DB::table('invoices')->where('company_id', $companyId)->whereNotIn('status', ['draft', 'cancelled', 'paid'])
            ->where('invoice_date', '<=', $f['to'])->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->selectRaw('COALESCE(SUM((total_amount - amount_paid) / '.$this->rate('invoices').'), 0) AS v')->value('v');
        $payable = DB::table('supplier_bills')->where('company_id', $companyId)->where('status', '!=', 'paid')
            ->where('bill_date', '<=', $f['to'])
            ->selectRaw('COALESCE(SUM((total_amount - amount_paid) / '.$this->rate('supplier_bills').'), 0) AS v')->value('v');
        $stockValue = DB::table('stock_levels')->join('products', 'products.id', '=', 'stock_levels.product_id')
            ->join('warehouses', 'warehouses.id', '=', 'stock_levels.warehouse_id')
            ->where('products.company_id', $companyId)->when($branchId, fn ($q) => $q->where('warehouses.branch_id', $branchId))
            ->selectRaw('COALESCE(SUM(stock_levels.quantity_on_hand * products.cost_price), 0) AS v')->value('v');

        // Revenue and expenses per month (per day for a period of a month or less).
        $daily = (strtotime($f['to']) - strtotime($f['from'])) <= 31 * 86400;
        $trend = $this->ledgerLines($companyId, $f)
            ->join('chart_of_accounts', 'chart_of_accounts.id', '=', 'journal_entry_lines.account_id')
            ->whereIn('chart_of_accounts.type', ['revenue', 'expense'])
            ->selectRaw('journal_entries.entry_date AS d, chart_of_accounts.type AS t, SUM(journal_entry_lines.debit) AS dr, SUM(journal_entry_lines.credit) AS cr')
            ->groupBy('journal_entries.entry_date', 'chart_of_accounts.type')->get()
            ->groupBy(fn ($r) => $daily ? substr((string) $r->d, 0, 10) : substr((string) $r->d, 0, 7))
            ->map(fn ($g, $period) => [
                'period' => $period,
                'revenue' => round($g->where('t', 'revenue')->sum(fn ($r) => $r->cr - $r->dr), 2),
                'expenses' => round($g->where('t', 'expense')->sum(fn ($r) => $r->dr - $r->cr), 2),
            ])->sortKeys()->values();

        $kpis = [
            ['key' => 'revenue', 'label' => 'Revenue', 'value' => $pl['revenue']['total'], 'type' => 'money'],
            ['key' => 'cogs', 'label' => 'Cost of sales', 'value' => $pl['cogs']['total'], 'type' => 'money'],
            ['key' => 'gross_profit', 'label' => 'Gross profit', 'value' => $pl['gross_profit'], 'type' => 'money'],
            ['key' => 'expenses', 'label' => 'Operating expenses', 'value' => $pl['operating_expenses']['total'], 'type' => 'money'],
            ['key' => 'net_profit', 'label' => 'Net profit', 'value' => $pl['net_profit'], 'type' => 'money'],
            ['key' => 'collected', 'label' => 'Cash collected', 'value' => round($collected, 2), 'type' => 'money'],
            ['key' => 'pos', 'label' => 'POS sales', 'value' => round((float) $sales->revenue, 2), 'type' => 'money', 'sub' => (int) $sales->n.' sales'],
            ['key' => 'invoiced', 'label' => 'Invoiced', 'value' => round((float) $invoices->revenue, 2), 'type' => 'money', 'sub' => (int) $invoices->n.' invoices'],
            ['key' => 'purchases', 'label' => 'Supplier bills', 'value' => round((float) $bills, 2), 'type' => 'money'],
            ['key' => 'receivable', 'label' => 'Owed to us', 'value' => round((float) $receivable, 2), 'type' => 'money'],
            ['key' => 'payable', 'label' => 'We owe suppliers', 'value' => round((float) $payable, 2), 'type' => 'money'],
            ['key' => 'stock', 'label' => 'Stock value (cost)', 'value' => round((float) $stockValue, 2), 'type' => 'money'],
        ];

        return [
            'kpis' => $kpis,
            'columns' => [
                $this->col('period', $daily ? 'Day' : 'Month', 'text'),
                $this->col('revenue', 'Revenue', 'money'),
                $this->col('expenses', 'Expenses', 'money'),
                $this->col('net', 'Net', 'money'),
            ],
            'rows' => $trend->map(fn ($r) => $r + ['net' => round($r['revenue'] - $r['expenses'], 2)])->all(),
            'totals' => $this->sumColumns($trend->map(fn ($r) => $r + ['net' => $r['revenue'] - $r['expenses']]), ['revenue', 'expenses', 'net']),
        ];
    }

    // ── Revenue / expense accounts (from the ledger) ───────────────────────

    /** Every account of the type — including ones with nothing posted — with its amount for the period. */
    protected function accounts(int $companyId, array $f, string $type): array
    {
        $byMonth = ($f['group_by'] ?? 'account') === 'month';
        $debitNormal = $type === 'expense';
        $cogsIds = $type === 'expense' ? ProfitAndLossService::cogsAccountIds($companyId) : [];

        $lines = $this->ledgerLines($companyId, $f)
            ->join('chart_of_accounts', 'chart_of_accounts.id', '=', 'journal_entry_lines.account_id')
            ->where('chart_of_accounts.type', $type)
            ->selectRaw('journal_entry_lines.account_id, journal_entries.entry_date AS d, SUM(journal_entry_lines.debit) AS dr, SUM(journal_entry_lines.credit) AS cr')
            ->groupBy('journal_entry_lines.account_id', 'journal_entries.entry_date')->get();
        $amount = fn ($g) => round($g->sum(fn ($r) => $debitNormal ? $r->dr - $r->cr : $r->cr - $r->dr), 2);

        $accounts = DB::table('chart_of_accounts')->where('company_id', $companyId)->where('type', $type)
            ->where(fn ($q) => $q->where('is_active', true)->orWhereIn('id', $lines->pluck('account_id')->unique()))
            ->orderBy('code')->get(['id', 'code', 'name']);

        if ($byMonth) {
            $rows = $lines->groupBy(fn ($r) => $r->account_id.'|'.substr((string) $r->d, 0, 7))
                ->map(function ($g, $key) use ($accounts, $amount) {
                    [$id, $month] = explode('|', $key);
                    $a = $accounts->firstWhere('id', (int) $id);

                    return ['month' => $month, 'code' => $a->code ?? '', 'name' => $a->name ?? '', 'amount' => $amount($g)];
                })->sortBy(fn ($r) => $r['month'].$r['code'])->values();

            return [
                'columns' => [$this->col('month', 'Month', 'text'), $this->col('code', 'Code', 'text'), $this->col('name', 'Account', 'text'), $this->col('amount', 'Amount', 'money')],
                'rows' => $rows->all(),
                'totals' => $this->sumColumns($rows, ['amount']),
            ];
        }

        $byAccount = $lines->groupBy('account_id');
        $rows = $accounts->map(fn ($a) => [
            'code' => $a->code,
            'name' => $a->name,
            'group' => $type === 'expense' ? (in_array((int) $a->id, $cogsIds, true) ? 'Cost of sales' : 'Operating expense') : 'Revenue',
            'entries' => $byAccount->has($a->id) ? $byAccount[$a->id]->count() : 0,
            'amount' => $byAccount->has($a->id) ? $amount($byAccount[$a->id]) : 0.0,
        ]);
        $total = $rows->sum('amount');
        $rows = $rows->map(fn ($r) => $r + ['share' => $total != 0 ? round($r['amount'] / $total * 100, 2) : 0.0]);

        return [
            'columns' => array_values(array_filter([
                $this->col('code', 'Code', 'text'),
                $this->col('name', 'Account', 'text'),
                $type === 'expense' ? $this->col('group', 'Type', 'text') : null,
                $this->col('entries', 'Posting days', 'number'),
                $this->col('amount', 'Amount', 'money'),
                $this->col('share', '% of total', 'percent'),
            ])),
            'rows' => $rows->values()->all(),
            'totals' => $this->sumColumns($rows, ['amount', 'entries']) + ['share' => $total != 0 ? 100.0 : 0.0],
        ];
    }

    // ── Revenue from POS sales and invoices ─────────────────────────────────

    protected function revenue(int $companyId, array $f): array
    {
        $groupBy = $f['group_by'] ?? 'customer';
        $source = $f['source'] ?? 'all';
        $lineLevel = in_array($groupBy, ['product', 'category'], true);

        $rows = collect();
        if ($source !== 'invoice') {
            $rows = $rows->concat($lineLevel ? $this->saleLines($companyId, $f) : $this->saleDocs($companyId, $f));
        }
        if ($source !== 'pos') {
            $rows = $rows->concat($lineLevel ? $this->invoiceLines($companyId, $f) : $this->invoiceDocs($companyId, $f));
        }

        if ($groupBy === 'document') {
            $rows = $rows->sortBy('date')->values();

            return [
                'columns' => [
                    $this->col('date', 'Date', 'date'), $this->col('reference', 'Reference', 'text'), $this->col('source', 'Type', 'text'),
                    $this->col('customer', 'Customer', 'text'), $this->col('branch', 'Branch', 'text'), $this->col('staff', 'By', 'text'),
                    $this->col('status', 'Status', 'text'), $this->col('revenue', 'Revenue', 'money'), $this->col('tax', 'Tax', 'money'),
                    $this->col('total', 'Total', 'money'), $this->col('paid', 'Paid', 'money'), $this->col('balance', 'Balance', 'money'),
                ],
                'rows' => $rows->all(),
                'totals' => $this->sumColumns($rows, ['revenue', 'tax', 'total', 'paid', 'balance']),
            ];
        }

        $label = ['customer' => 'Customer', 'product' => 'Product', 'category' => 'Category', 'branch' => 'Branch', 'staff' => 'Sold / invoiced by', 'day' => 'Day', 'month' => 'Month'][$groupBy] ?? 'Group';
        $keyOf = fn ($r) => match ($groupBy) {
            'day' => substr($r['date'], 0, 10),
            'month' => substr($r['date'], 0, 7),
            default => $r[$groupBy] ?? '—',
        };

        if ($lineLevel) {
            $grouped = $rows->groupBy($keyOf)->map(fn ($g, $k) => [
                'group' => $k, 'quantity' => round($g->sum('quantity'), 2), 'revenue' => round($g->sum('revenue'), 2),
                'cost' => round($g->sum('cost'), 2), 'margin' => round($g->sum('revenue') - $g->sum('cost'), 2),
            ]);
            $columns = [$this->col('group', $label, 'text'), $this->col('quantity', 'Qty sold', 'number'), $this->col('revenue', 'Revenue', 'money'), $this->col('cost', 'Cost', 'money'), $this->col('margin', 'Margin', 'money'), $this->col('share', '% of revenue', 'percent')];
            $sumKeys = ['quantity', 'revenue', 'cost', 'margin'];
        } else {
            $grouped = $rows->groupBy($keyOf)->map(fn ($g, $k) => [
                'group' => $k, 'documents' => $g->count(), 'revenue' => round($g->sum('revenue'), 2), 'tax' => round($g->sum('tax'), 2),
                'total' => round($g->sum('total'), 2), 'paid' => round($g->sum('paid'), 2), 'balance' => round($g->sum('balance'), 2),
            ]);
            $columns = [$this->col('group', $label, 'text'), $this->col('documents', 'Sales / invoices', 'number'), $this->col('revenue', 'Revenue', 'money'), $this->col('tax', 'Tax', 'money'), $this->col('total', 'Total', 'money'), $this->col('paid', 'Paid', 'money'), $this->col('balance', 'Balance', 'money'), $this->col('share', '% of revenue', 'percent')];
            $sumKeys = ['documents', 'revenue', 'tax', 'total', 'paid', 'balance'];
        }

        $sorted = in_array($groupBy, ['day', 'month'], true) ? $grouped->sortKeys() : $grouped->sortByDesc('revenue');
        $total = $grouped->sum('revenue');
        $out = $sorted->values()->map(fn ($r) => $r + ['share' => $total != 0 ? round($r['revenue'] / $total * 100, 2) : 0.0]);

        return ['columns' => $columns, 'rows' => $out->all(), 'totals' => $this->sumColumns($out, $sumKeys) + ['share' => $total != 0 ? 100.0 : 0.0]];
    }

    protected function saleDocs(int $companyId, array $f): Collection
    {
        return $this->salesQuery($companyId, $f)
            ->leftJoin('customers', 'customers.id', '=', 'sales.customer_id')
            ->leftJoin('branches', 'branches.id', '=', 'sales.branch_id')
            ->leftJoin('users', 'users.id', '=', 'sales.served_by')
            ->get(['sales.sale_date AS date', 'sales.reference_number', 'sales.status', 'sales.subtotal', 'sales.discount_amount', 'sales.tax_amount', 'sales.total_amount', 'sales.amount_paid', 'customers.name AS customer', 'branches.name AS branch', 'users.name AS staff'])
            ->map(fn ($s) => [
                'date' => substr((string) $s->date, 0, 10), 'reference' => $s->reference_number, 'source' => 'POS sale',
                'customer' => $s->customer ?: 'Walk-in', 'branch' => $s->branch ?: '—', 'staff' => $s->staff ?: '—', 'status' => $s->status,
                'revenue' => round($s->subtotal - $s->discount_amount, 2), 'tax' => round((float) $s->tax_amount, 2),
                'total' => round((float) $s->total_amount, 2), 'paid' => round(min((float) $s->amount_paid, (float) $s->total_amount), 2),
                'balance' => round(max((float) $s->total_amount - (float) $s->amount_paid, 0), 2),
            ]);
    }

    protected function invoiceDocs(int $companyId, array $f): Collection
    {
        return $this->invoicesQuery($companyId, $f)
            ->leftJoin('customers', 'customers.id', '=', 'invoices.customer_id')
            ->leftJoin('branches', 'branches.id', '=', 'invoices.branch_id')
            ->leftJoin('users', 'users.id', '=', 'invoices.created_by')
            ->get(['invoices.invoice_date AS date', 'invoices.reference_number', 'invoices.status', 'invoices.subtotal', 'invoices.discount_amount', 'invoices.tax_amount', 'invoices.total_amount', 'invoices.amount_paid', 'invoices.exchange_rate', 'customers.name AS customer', 'branches.name AS branch', 'users.name AS staff'])
            ->map(function ($i) {
                $r = (float) $i->exchange_rate ?: 1.0;

                return [
                    'date' => substr((string) $i->date, 0, 10), 'reference' => $i->reference_number, 'source' => 'Invoice',
                    'customer' => $i->customer ?: '—', 'branch' => $i->branch ?: '—', 'staff' => $i->staff ?: '—', 'status' => $i->status,
                    'revenue' => round(($i->subtotal - $i->discount_amount) / $r, 2), 'tax' => round($i->tax_amount / $r, 2),
                    'total' => round($i->total_amount / $r, 2), 'paid' => round($i->amount_paid / $r, 2),
                    'balance' => round(max($i->total_amount - $i->amount_paid, 0) / $r, 2),
                ];
            });
    }

    protected function saleLines(int $companyId, array $f): Collection
    {
        return $this->salesQuery($companyId, $f)
            ->join('sale_items', 'sale_items.sale_id', '=', 'sales.id')
            ->leftJoin('products', 'products.id', '=', 'sale_items.product_id')
            ->leftJoin('categories', 'categories.id', '=', 'products.category_id')
            ->get(['sales.sale_date AS date', 'sales.subtotal', 'sales.discount_amount', 'sale_items.quantity', 'sale_items.line_total', 'products.name AS product', 'products.cost_price', 'categories.name AS category'])
            ->map(fn ($l) => $this->lineRow($l, 1.0));
    }

    protected function invoiceLines(int $companyId, array $f): Collection
    {
        return $this->invoicesQuery($companyId, $f)
            ->join('invoice_items', 'invoice_items.invoice_id', '=', 'invoices.id')
            ->leftJoin('products', 'products.id', '=', 'invoice_items.product_id')
            ->leftJoin('categories', 'categories.id', '=', 'products.category_id')
            ->get(['invoices.invoice_date AS date', 'invoices.subtotal', 'invoices.discount_amount', 'invoices.exchange_rate', 'invoice_items.quantity', 'invoice_items.line_total', 'products.name AS product', 'products.cost_price', 'categories.name AS category'])
            ->map(fn ($l) => $this->lineRow($l, (float) $l->exchange_rate ?: 1.0));
    }

    /** One sold line: its revenue after its share of the document discount, in base currency. */
    protected function lineRow(object $l, float $rate): array
    {
        $share = (float) $l->subtotal > 0 ? (float) $l->discount_amount / (float) $l->subtotal : 0.0;

        return [
            'date' => substr((string) $l->date, 0, 10),
            'product' => $l->product ?: 'Item without product',
            'category' => $l->category ?: 'Uncategorised',
            'quantity' => (float) $l->quantity,
            'revenue' => round((float) $l->line_total * (1 - $share) / $rate, 2),
            'cost' => round((float) $l->quantity * (float) $l->cost_price, 2),
        ];
    }

    // ── Collections (money received) ────────────────────────────────────────

    protected function collections(int $companyId, array $f): array
    {
        $groupBy = $f['group_by'] ?? 'method';
        $rows = collect($this->collectionRows($companyId, $f, $f['source'] ?? 'all'));

        if ($groupBy === 'document') {
            $rows = $rows->sortBy('date')->values();

            return [
                'columns' => [$this->col('date', 'Date', 'date'), $this->col('reference', 'Reference', 'text'), $this->col('source', 'For', 'text'), $this->col('customer', 'Customer', 'text'), $this->col('method', 'Method', 'text'), $this->col('staff', 'Received by', 'text'), $this->col('amount', 'Amount', 'money')],
                'rows' => $rows->all(),
                'totals' => $this->sumColumns($rows, ['amount']),
            ];
        }

        return $this->grouped($rows, $groupBy, [
            'method' => 'Payment method', 'staff' => 'Received by', 'customer' => 'Customer', 'source' => 'For',
        ], 'amount', 'Collected', 'Payments');
    }

    protected function collectionRows(int $companyId, array $f, string $source): array
    {
        $rows = [];
        if ($source !== 'invoice') {
            $q = DB::table('sale_payments')->join('sales', 'sales.id', '=', 'sale_payments.sale_id')
                ->leftJoin('payment_types', 'payment_types.id', '=', 'sale_payments.payment_type_id')
                ->leftJoin('customers', 'customers.id', '=', 'sales.customer_id')
                ->leftJoin('users', 'users.id', '=', 'sales.served_by')
                ->where('sales.company_id', $companyId)->where('sales.status', 'completed')
                ->whereBetween(DB::raw('CAST(COALESCE(sale_payments.paid_at, sales.sale_date) AS DATE)'), [$f['from'], $f['to']])
                ->when($f['branch_id'] ?? null, fn ($q, $b) => $q->where('sales.branch_id', $b));
            foreach ($q->get(['sale_payments.paid_at', 'sales.sale_date', 'sales.reference_number', 'sale_payments.amount', 'payment_types.name AS method', 'customers.name AS customer', 'users.name AS staff']) as $p) {
                $rows[] = [
                    'date' => substr((string) ($p->paid_at ?: $p->sale_date), 0, 10), 'reference' => $p->reference_number, 'source' => 'POS sale',
                    'customer' => $p->customer ?: 'Walk-in', 'method' => $p->method ?: '—', 'staff' => $p->staff ?: '—', 'amount' => round((float) $p->amount, 2),
                ];
            }
        }
        if ($source !== 'pos') {
            $q = DB::table('customer_payments')
                ->leftJoin('invoices', 'invoices.id', '=', 'customer_payments.invoice_id')
                ->leftJoin('payment_types', 'payment_types.id', '=', 'customer_payments.payment_type_id')
                ->leftJoin('customers', 'customers.id', '=', 'customer_payments.customer_id')
                ->leftJoin('users', 'users.id', '=', 'customer_payments.received_by')
                ->where('customer_payments.company_id', $companyId)
                ->whereBetween('customer_payments.payment_date', [$f['from'], $f['to']])
                ->when($f['branch_id'] ?? null, fn ($q, $b) => $q->where('invoices.branch_id', $b));
            foreach ($q->get(['customer_payments.payment_date', 'customer_payments.reference_number', 'invoices.reference_number AS invoice_ref', 'customer_payments.amount', 'customer_payments.exchange_rate', 'payment_types.name AS method', 'customers.name AS customer', 'users.name AS staff']) as $p) {
                $rows[] = [
                    'date' => substr((string) $p->payment_date, 0, 10), 'reference' => $p->reference_number ?: $p->invoice_ref, 'source' => 'Invoice payment',
                    'customer' => $p->customer ?: '—', 'method' => $p->method ?: '—', 'staff' => $p->staff ?: '—',
                    'amount' => round((float) $p->amount / ((float) $p->exchange_rate ?: 1.0), 2),
                ];
            }
        }

        return $rows;
    }

    // ── Purchases & supplier payments ───────────────────────────────────────

    protected function purchases(int $companyId, array $f): array
    {
        $groupBy = $f['group_by'] ?? 'supplier';
        $rows = $this->billsQuery($companyId, $f)
            ->leftJoin('suppliers', 'suppliers.id', '=', 'supplier_bills.supplier_id')
            ->when($f['status'] ?? null, fn ($q, $s) => $q->where('supplier_bills.status', $s))
            ->get(['supplier_bills.bill_date', 'supplier_bills.due_date', 'supplier_bills.reference_number', 'supplier_bills.status', 'supplier_bills.subtotal', 'supplier_bills.tax_amount', 'supplier_bills.total_amount', 'supplier_bills.amount_paid', 'supplier_bills.exchange_rate', 'suppliers.name AS supplier'])
            ->map(function ($b) {
                $r = (float) $b->exchange_rate ?: 1.0;

                return [
                    'date' => substr((string) $b->bill_date, 0, 10), 'due' => $b->due_date ? substr((string) $b->due_date, 0, 10) : null,
                    'reference' => $b->reference_number, 'supplier' => $b->supplier ?: '—', 'status' => $b->status,
                    'amount' => round($b->total_amount / $r, 2), 'paid' => round($b->amount_paid / $r, 2),
                    'balance' => round(max($b->total_amount - $b->amount_paid, 0) / $r, 2),
                ];
            });

        if ($groupBy === 'document') {
            $rows = $rows->sortBy('date')->values();

            return [
                'columns' => [$this->col('date', 'Bill date', 'date'), $this->col('due', 'Due', 'date'), $this->col('reference', 'Reference', 'text'), $this->col('supplier', 'Supplier', 'text'), $this->col('status', 'Status', 'text'), $this->col('amount', 'Amount', 'money'), $this->col('paid', 'Paid', 'money'), $this->col('balance', 'Balance', 'money')],
                'rows' => $rows->all(),
                'totals' => $this->sumColumns($rows, ['amount', 'paid', 'balance']),
            ];
        }

        return $this->grouped($rows, $groupBy, ['supplier' => 'Supplier', 'status' => 'Status'], 'amount', 'Billed', 'Bills', ['paid' => 'Paid', 'balance' => 'Balance']);
    }

    protected function supplierPayments(int $companyId, array $f): array
    {
        $groupBy = $f['group_by'] ?? 'supplier';
        $rows = DB::table('supplier_payments')
            ->leftJoin('suppliers', 'suppliers.id', '=', 'supplier_payments.supplier_id')
            ->leftJoin('supplier_bills', 'supplier_bills.id', '=', 'supplier_payments.supplier_bill_id')
            ->leftJoin('payment_types', 'payment_types.id', '=', 'supplier_payments.payment_type_id')
            ->leftJoin('users', 'users.id', '=', 'supplier_payments.paid_by')
            ->where('supplier_payments.company_id', $companyId)
            ->whereBetween('supplier_payments.payment_date', [$f['from'], $f['to']])
            ->get(['supplier_payments.payment_date', 'supplier_payments.reference_number', 'supplier_bills.reference_number AS bill_ref', 'supplier_payments.amount', 'supplier_payments.exchange_rate', 'suppliers.name AS supplier', 'payment_types.name AS method', 'users.name AS staff'])
            ->map(fn ($p) => [
                'date' => substr((string) $p->payment_date, 0, 10), 'reference' => $p->reference_number ?: '—', 'bill' => $p->bill_ref ?: '—',
                'supplier' => $p->supplier ?: '—', 'method' => $p->method ?: '—', 'staff' => $p->staff ?: '—',
                'amount' => round((float) $p->amount / ((float) $p->exchange_rate ?: 1.0), 2),
            ]);

        if ($groupBy === 'document') {
            $rows = $rows->sortBy('date')->values();

            return [
                'columns' => [$this->col('date', 'Date', 'date'), $this->col('reference', 'Reference', 'text'), $this->col('bill', 'Bill', 'text'), $this->col('supplier', 'Supplier', 'text'), $this->col('method', 'Method', 'text'), $this->col('staff', 'Paid by', 'text'), $this->col('amount', 'Amount', 'money')],
                'rows' => $rows->all(),
                'totals' => $this->sumColumns($rows, ['amount']),
            ];
        }

        return $this->grouped($rows, $groupBy, ['supplier' => 'Supplier', 'method' => 'Payment method', 'staff' => 'Paid by'], 'amount', 'Paid', 'Payments');
    }

    // ── Inventory ───────────────────────────────────────────────────────────

    /** Stock on hand now, at cost and at selling price. */
    protected function stock(int $companyId, array $f): array
    {
        $groupBy = $f['group_by'] ?? 'product';
        $rows = DB::table('stock_levels')
            ->join('products', 'products.id', '=', 'stock_levels.product_id')
            ->join('warehouses', 'warehouses.id', '=', 'stock_levels.warehouse_id')
            ->leftJoin('categories', 'categories.id', '=', 'products.category_id')
            ->where('products.company_id', $companyId)
            ->when($f['branch_id'] ?? null, fn ($q, $b) => $q->where('warehouses.branch_id', $b))
            ->get(['products.name AS product', 'products.sku', 'products.reorder_level', 'products.cost_price', 'products.selling_price', 'warehouses.name AS warehouse', 'categories.name AS category', 'stock_levels.quantity_on_hand', 'stock_levels.quantity_reserved'])
            ->map(fn ($s) => [
                'product' => $s->product, 'sku' => $s->sku ?: '—', 'warehouse' => $s->warehouse, 'category' => $s->category ?: 'Uncategorised',
                'on_hand' => (float) $s->quantity_on_hand, 'reserved' => (float) $s->quantity_reserved, 'reorder_level' => (float) $s->reorder_level,
                'cost_value' => round($s->quantity_on_hand * $s->cost_price, 2), 'retail_value' => round($s->quantity_on_hand * $s->selling_price, 2),
            ]);

        if (($f['status'] ?? null) === 'low') {
            $rows = $rows->filter(fn ($r) => $r['on_hand'] <= $r['reorder_level']);
        }

        if ($groupBy === 'product' || $groupBy === 'document') {
            $rows = $rows->sortBy('product')->values();

            return [
                'columns' => [$this->col('product', 'Product', 'text'), $this->col('sku', 'SKU', 'text'), $this->col('category', 'Category', 'text'), $this->col('warehouse', 'Warehouse', 'text'), $this->col('on_hand', 'On hand', 'number'), $this->col('reserved', 'Reserved', 'number'), $this->col('reorder_level', 'Reorder level', 'number'), $this->col('cost_value', 'Value at cost', 'money'), $this->col('retail_value', 'Value at selling price', 'money')],
                'rows' => $rows->all(),
                'totals' => $this->sumColumns($rows, ['on_hand', 'reserved', 'cost_value', 'retail_value']),
            ];
        }

        $label = ['warehouse' => 'Warehouse', 'category' => 'Category'][$groupBy] ?? 'Group';
        $out = $rows->groupBy($groupBy)->map(fn ($g, $k) => [
            'group' => $k, 'lines' => $g->count(), 'on_hand' => round($g->sum('on_hand'), 2),
            'cost_value' => round($g->sum('cost_value'), 2), 'retail_value' => round($g->sum('retail_value'), 2),
        ])->sortByDesc('cost_value')->values();

        return [
            'columns' => [$this->col('group', $label, 'text'), $this->col('lines', 'Products', 'number'), $this->col('on_hand', 'Units on hand', 'number'), $this->col('cost_value', 'Value at cost', 'money'), $this->col('retail_value', 'Value at selling price', 'money')],
            'rows' => $out->all(),
            'totals' => $this->sumColumns($out, ['lines', 'on_hand', 'cost_value', 'retail_value']),
        ];
    }

    protected function stockMovements(int $companyId, array $f): array
    {
        $groupBy = $f['group_by'] ?? 'product';
        $rows = DB::table('stock_movements')
            ->join('products', 'products.id', '=', 'stock_movements.product_id')
            ->leftJoin('warehouses', 'warehouses.id', '=', 'stock_movements.warehouse_id')
            ->leftJoin('users', 'users.id', '=', 'stock_movements.performed_by')
            ->where('stock_movements.company_id', $companyId)
            ->whereBetween(DB::raw('CAST(stock_movements.moved_at AS DATE)'), [$f['from'], $f['to']])
            ->when($f['branch_id'] ?? null, fn ($q, $b) => $q->where('warehouses.branch_id', $b))
            ->when($f['status'] ?? null, fn ($q, $t) => $q->where('stock_movements.movement_type', $t))
            ->get(['stock_movements.moved_at', 'stock_movements.movement_type', 'stock_movements.quantity', 'stock_movements.reason', 'products.name AS product', 'warehouses.name AS warehouse', 'users.name AS staff'])
            ->map(function ($m) {
                $qty = (float) $m->quantity;
                $out = in_array($m->movement_type, ['sale', 'transfer_out'], true) || ($m->movement_type === 'adjustment' && $qty < 0);

                return [
                    'date' => substr((string) $m->moved_at, 0, 10), 'product' => $m->product, 'warehouse' => $m->warehouse ?: '—',
                    'type' => str_replace('_', ' ', $m->movement_type), 'staff' => $m->staff ?: '—', 'reason' => $m->reason ?: '',
                    'in' => $out ? 0.0 : abs($qty), 'out' => $out ? abs($qty) : 0.0,
                ];
            });

        if ($groupBy === 'document') {
            $rows = $rows->sortBy('date')->values();

            return [
                'columns' => [$this->col('date', 'Date', 'date'), $this->col('product', 'Product', 'text'), $this->col('warehouse', 'Warehouse', 'text'), $this->col('type', 'Movement', 'text'), $this->col('in', 'In', 'number'), $this->col('out', 'Out', 'number'), $this->col('staff', 'By', 'text'), $this->col('reason', 'Reason', 'text')],
                'rows' => $rows->all(),
                'totals' => $this->sumColumns($rows, ['in', 'out']),
            ];
        }

        $label = ['product' => 'Product', 'type' => 'Movement', 'warehouse' => 'Warehouse'][$groupBy] ?? 'Group';
        $out = $rows->groupBy($groupBy)->map(fn ($g, $k) => [
            'group' => $k, 'movements' => $g->count(), 'in' => round($g->sum('in'), 2), 'out' => round($g->sum('out'), 2), 'net' => round($g->sum('in') - $g->sum('out'), 2),
        ])->sortKeys()->values();

        return [
            'columns' => [$this->col('group', $label, 'text'), $this->col('movements', 'Movements', 'number'), $this->col('in', 'In', 'number'), $this->col('out', 'Out', 'number'), $this->col('net', 'Net', 'number')],
            'rows' => $out->all(),
            'totals' => $this->sumColumns($out, ['movements', 'in', 'out', 'net']),
        ];
    }

    // ── Payroll ─────────────────────────────────────────────────────────────

    /** Payslips of runs whose period ends in the range (processed or paid runs only). */
    protected function payroll(int $companyId, array $f): array
    {
        $groupBy = $f['group_by'] ?? 'employee';
        $rows = DB::table('payslips')
            ->join('payroll_runs', 'payroll_runs.id', '=', 'payslips.payroll_run_id')
            ->join('employees', 'employees.id', '=', 'payslips.employee_id')
            ->leftJoin('departments', 'departments.id', '=', 'employees.department_id')
            ->where('payroll_runs.company_id', $companyId)
            ->whereIn('payroll_runs.status', ['processed', 'paid'])
            ->whereBetween('payroll_runs.period_end', [$f['from'], $f['to']])
            ->when($f['branch_id'] ?? null, fn ($q, $b) => $q->where('payroll_runs.branch_id', $b))
            ->get(['payroll_runs.period_start', 'payroll_runs.period_end', 'payroll_runs.status', 'employees.employee_code', 'employees.first_name', 'employees.last_name', 'departments.name AS department', 'payslips.gross_pay', 'payslips.total_deductions', 'payslips.net_pay'])
            ->map(fn ($p) => [
                'run' => substr((string) $p->period_start, 0, 10).' – '.substr((string) $p->period_end, 0, 10).' ('.$p->status.')',
                'employee' => trim($p->first_name.' '.$p->last_name).' ('.$p->employee_code.')', 'department' => $p->department ?: 'No department',
                'gross' => round((float) $p->gross_pay, 2), 'deductions' => round((float) $p->total_deductions, 2), 'net' => round((float) $p->net_pay, 2),
            ]);

        $label = ['employee' => 'Employee', 'department' => 'Department', 'run' => 'Payroll run'][$groupBy] ?? 'Group';
        $out = $rows->groupBy($groupBy)->map(fn ($g, $k) => [
            'group' => $k, 'payslips' => $g->count(), 'gross' => round($g->sum('gross'), 2), 'deductions' => round($g->sum('deductions'), 2), 'net' => round($g->sum('net'), 2),
        ])->sortKeys()->values();

        return [
            'columns' => [$this->col('group', $label, 'text'), $this->col('payslips', 'Payslips', 'number'), $this->col('gross', 'Gross pay', 'money'), $this->col('deductions', 'Deductions', 'money'), $this->col('net', 'Net pay', 'money')],
            'rows' => $out->all(),
            'totals' => $this->sumColumns($out, ['payslips', 'gross', 'deductions', 'net']),
        ];
    }

    // ── Audit log ───────────────────────────────────────────────────────────

    protected function audit(int $companyId, array $f): array
    {
        $groupBy = $f['group_by'] ?? 'document';
        $q = DB::table('audit_logs')->leftJoin('users', 'users.id', '=', 'audit_logs.user_id')
            ->where('audit_logs.company_id', $companyId)
            ->whereBetween(DB::raw('CAST(audit_logs.created_at AS DATE)'), [$f['from'], $f['to']]);

        if ($groupBy === 'document') {
            $rows = $q->orderByDesc('audit_logs.created_at')->limit(5000)
                ->get(['audit_logs.created_at', 'audit_logs.action', 'audit_logs.module', 'audit_logs.record_id', 'audit_logs.ip_address', 'users.name AS user'])
                ->map(fn ($a) => [
                    'when' => substr((string) $a->created_at, 0, 16), 'user' => $a->user ?: 'System', 'module' => $a->module ?: '—',
                    'action' => $a->action, 'record' => $a->record_id ? (string) $a->record_id : '', 'ip' => $a->ip_address ?: '',
                ]);

            return [
                'columns' => [$this->col('when', 'When', 'text'), $this->col('user', 'User', 'text'), $this->col('module', 'Module', 'text'), $this->col('action', 'Action', 'text'), $this->col('record', 'Record', 'text'), $this->col('ip', 'IP address', 'text')],
                'rows' => $rows->all(),
                'totals' => ['when' => $rows->count().' entries'],
            ];
        }

        $column = ['user' => 'users.name', 'module' => 'audit_logs.module', 'action' => 'audit_logs.action'][$groupBy] ?? 'audit_logs.action';
        $rows = $q->selectRaw("COALESCE({$column}, '—') AS grp, COUNT(*) AS n, MAX(audit_logs.created_at) AS last_at")
            ->groupBy(DB::raw($column))->orderByDesc('n')->get()
            ->map(fn ($r) => ['group' => $r->grp, 'actions' => (int) $r->n, 'last' => substr((string) $r->last_at, 0, 16)]);

        return [
            'columns' => [$this->col('group', ['user' => 'User', 'module' => 'Module', 'action' => 'Action'][$groupBy] ?? 'Group', 'text'), $this->col('actions', 'Actions', 'number'), $this->col('last', 'Last at', 'text')],
            'rows' => $rows->all(),
            'totals' => $this->sumColumns($rows, ['actions']),
        ];
    }

    // ── Shared pieces ───────────────────────────────────────────────────────

    protected function salesQuery(int $companyId, array $f): Builder
    {
        return DB::table('sales')->where('sales.company_id', $companyId)->where('sales.status', 'completed')
            ->whereBetween(DB::raw('CAST(sales.sale_date AS DATE)'), [$f['from'], $f['to']])
            ->when($f['branch_id'] ?? null, fn ($q, $b) => $q->where('sales.branch_id', $b));
    }

    protected function invoicesQuery(int $companyId, array $f): Builder
    {
        return DB::table('invoices')->where('invoices.company_id', $companyId)->whereIn('invoices.status', self::INVOICE_LIVE)
            ->whereBetween('invoices.invoice_date', [$f['from'], $f['to']])
            ->when($f['branch_id'] ?? null, fn ($q, $b) => $q->where('invoices.branch_id', $b));
    }

    protected function billsQuery(int $companyId, array $f): Builder
    {
        return DB::table('supplier_bills')->where('supplier_bills.company_id', $companyId)
            ->whereBetween('supplier_bills.bill_date', [$f['from'], $f['to']]);
    }

    /** Posted journal lines in the period (the ledger every accounting report reads). */
    protected function ledgerLines(int $companyId, array $f): Builder
    {
        return DB::table('journal_entry_lines')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_entry_lines.journal_entry_id')
            ->where('journal_entries.company_id', $companyId)
            ->whereIn('journal_entries.status', JournalEntry::LEDGER_STATUSES)
            ->whereBetween('journal_entries.entry_date', [$f['from'], $f['to']])
            ->when($f['branch_id'] ?? null, fn ($q, $b) => $q->where('journal_entries.branch_id', $b));
    }

    protected function rate(string $table): string
    {
        return "COALESCE(NULLIF({$table}.exchange_rate, 0), 1)";
    }

    /** Group document rows by a field (or by day/month of their date) and total one amount. */
    protected function grouped(Collection $rows, string $groupBy, array $labels, string $amountKey, string $amountLabel, string $countLabel, array $extra = []): array
    {
        $label = ($labels + ['day' => 'Day', 'month' => 'Month'])[$groupBy] ?? 'Group';
        $keyOf = fn ($r) => match ($groupBy) {
            'day' => substr($r['date'], 0, 10),
            'month' => substr($r['date'], 0, 7),
            default => $r[$groupBy] ?? '—',
        };
        $grouped = $rows->groupBy($keyOf)->map(function ($g, $k) use ($amountKey, $extra) {
            $row = ['group' => $k, 'count' => $g->count(), $amountKey => round($g->sum($amountKey), 2)];
            foreach (array_keys($extra) as $key) {
                $row[$key] = round($g->sum($key), 2);
            }

            return $row;
        });
        $sorted = in_array($groupBy, ['day', 'month'], true) ? $grouped->sortKeys() : $grouped->sortByDesc($amountKey);
        $total = $grouped->sum($amountKey);
        $out = $sorted->values()->map(fn ($r) => $r + ['share' => $total != 0 ? round($r[$amountKey] / $total * 100, 2) : 0.0]);

        $columns = [$this->col('group', $label, 'text'), $this->col('count', $countLabel, 'number'), $this->col($amountKey, $amountLabel, 'money')];
        foreach ($extra as $key => $title) {
            $columns[] = $this->col($key, $title, 'money');
        }
        $columns[] = $this->col('share', '% of total', 'percent');

        return [
            'columns' => $columns,
            'rows' => $out->all(),
            'totals' => $this->sumColumns($out, array_merge(['count', $amountKey], array_keys($extra))) + ['share' => $total != 0 ? 100.0 : 0.0],
        ];
    }

    protected function col(string $key, string $label, string $type): array
    {
        return ['key' => $key, 'label' => $label, 'type' => $type];
    }

    protected function sumColumns(Collection $rows, array $keys): array
    {
        $totals = [];
        foreach ($keys as $key) {
            $totals[$key] = round($rows->sum($key), 2);
        }

        return $totals;
    }
}
