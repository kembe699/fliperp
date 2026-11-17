# Generation 711 (G711): FlipERP requirements check

**Client:** Generation 711, Juba (Save the Children partner). PO 30209672. Handover due **30 November 2026**.
**Correction (17 Nov 2026, during G3 work):** the P&L, Balance Sheet and Trial Balance pages already had a CSV **Download** button; the first version of this check missed it. G3 is about adding Excel and PDF.

**Checked:** 17 November 2026, against branch `deploy/digitalocean` at commit `e34767e`. This is the same commit running on the ERP droplet (161.35.83.157).
**Method:** each answer comes from reading the code (models, migrations, services, policies, routes, frontend screens), plus one **read-only** look at G711's own tenant (company id 6) on the droplet database. No code, data or configuration was changed.

## Status key

| Mark | Meaning |
|---|---|
| ✅ **Available** | Works today with configuration only |
| ⚠️ **Partial** | Works, but with a limitation or workaround (explained) |
| ❌ **Not available** | Needs development |
| ❓ **Unclear** | Could not confirm |

## What G711's tenant looks like today (read-only check, company id 6)

| Item | Current state | Action needed |
|---|---|---|
| Company | "Generation 711", code `G711`, status active, base currency **USD**, timezone **UTC**, no logo | Set timezone to Africa/Juba; upload logo |
| Modules switched on | POS & Sales, Inventory, **Procurement**, HR & Payroll, Assets, **Grants**, **Accounting** | **Budgeting and M&E are OFF.** Both are needed: Budgeting gives Budget vs Actual; M&E gives Projects |
| Self-approval | `allow_self_approval = true` | Turn **off** so the maker-checker rule applies |
| Chart of accounts | The 17 default accounts (generic business chart) | Replace or extend with an NGO chart (B1) |
| Accounting periods | None | Create July–June periods (A2) |
| Exchange rates | 1 row: SSP 7,800 on 2026-11-02 | Looks like a test rate. Confirm or replace |
| Donors / SOFs / cost centres | 1 each: donor "IOM", SOF "SOF-11 Farming" (USD 1,000, 5–9 Nov 2026), cost centre "CCJ WAU Office" | Look like **test data**: remove before go-live |
| Branches | 1: "HQ" (MAIN) | Rename to Juba |
| Payment types | 1: CASH | Add Bank transfer, Mobile money as needed |
| Suppliers, journals, POs, RFQs, bills, employees | None | Nothing posted yet. Good, a clean start |
| Users | 3 users: a Save the Children (`savethechildren.org`) company admin, and two Nile Hive (`nilehc.tech`) accounts holding **super_admin** / **platform_admin** roles | None of G711's 4 named users exist yet. Review the Nile Hive accounts with super/platform roles inside a client tenant (see questions) |

---

## Requirements table

### Organisation and accounts

| ID | Requirement | Status | Evidence (file / route / screen) | Notes / gap |
|---|---|---|---|---|
| A1 | Organisation profile: name, logo, address, phone, email, registration/tax no. | ⚠️ Partial | `app/Models/Company.php` (fillable: name, logo_url, currency_code, timezone). Screen **Settings › Company** (`CompanySettingsPage.tsx`, logo upload). Address and phone live on the branch: `branches.address`, `branches.phone` (**Settings › Branches**) | Name and logo: yes. Address and phone: only on the branch. **No company email, registration number or tax number fields** anywhere on the company. Printed documents show logo and name only (`Company::logoDataUri`, `document-header.blade.php`). Gap **G10** |
| A2 | Financial year **July to June** | ⚠️ Partial | No fiscal-year setting anywhere (searched `app`, `database/migrations`, `frontend/src` for fiscal/financial year). Periods: `accounting_periods` (free `start_date`/`end_date`), **Accounting › Accounting Periods**; closing blocks posting (`JournalEntryService::assertPeriodOpen`, line 226). P&L/TB/GL take any `from`/`to` (`ReportController`). Balance Sheet: `BalanceSheetService` | July–June **periods can be created by hand**, closed periods lock posting, and P&L can be run 1 Jul–30 Jun by typing the dates. Limits: (1) the **"This year"** shortcut on Reports Center and the Dashboard starts on **1 January** (`CenterReportPage.tsx:47`, `DashboardPage.tsx:45`, `lib/format.ts:51`); (2) the Balance Sheet shows one cumulative **net income since the first transaction**: there is **no year-end close** moving the year's surplus into accumulated funds. Fine for the first year (Oct 2026–Jun 2027); needed before the first year-end (30 Jun 2027). Gap **G9** |
| A3 | Base currency **USD** | ✅ Available | `companies.currency_code`; G711 tenant already USD | Base currency is fixed at onboarding; every report is kept in USD |
| A4 | Second currency **SSP**: receive USD, spend USD and SSP | ⚠️ Partial | Dated rates: `exchange_rates` table, `ExchangeRateService` (rate = SSP per 1 USD, latest on or before the document date), **Accounting › Exchange Rates**. Documents carry `currency_code` + `exchange_rate` (migration `2026_10_29_000002`): journal entries, supplier bills, supplier payments, invoices, customer payments. Lines store SSP amount (`transaction_debit/credit`) and USD amount (`debit/credit`). Realised FX gain/loss to account **5500** on supplier and customer payments (`SupplierPaymentService:65`, `CustomerPaymentService:62`). "Show in" any currency on reports (`ReportCurrencyConverter`) | **Yes:** SSP transactions convert at the dated rate when posted; the GL shows each line's own currency and amount; realised FX gain/loss is automatic on bill/invoice payments; any report can be **displayed** in SSP at one rate. **No:** accounts have **no currency of their own** (`chart_of_accounts` has none), so there is **no running SSP balance per bank/cash account**, and **no month-end revaluation (unrealised FX)** of SSP balances. Payroll posts in USD only (`PayrollRunService` passes no currency). Gaps **G1**, **G8** |
| B1 | Chart of accounts: our **standard NGO chart** or import theirs | ⚠️ Partial | Default chart: `ChartOfAccountService` (17 generic accounts: Sales Revenue, Cost of Goods Sold, Owner's Equity…), seeded via `POST chart-of-accounts/seed`. **Accounting › Chart of Accounts** allows add/edit with parent accounts. No import (no file input accepts CSV anywhere in `frontend/src`) | **There is no NGO chart template and no chart import.** Workaround: build G711's chart by hand in Chart of Accounts (about 40–60 accounts, ~3–4 hours for us). Accounts 1000, 2000, 2100, 2200, 3900, 5000, 5200, 5500 must keep their codes: modules post to them by code. Gap **G6** (optional) |
| B2 | Bank accounts in **USD and SSP** | ❌ Not available | All module payments post to the **single account code 1000** "Cash and Bank": `SupplierPaymentService.php:20`, `CustomerPaymentService.php:20`, `SaleService.php:32`, `CashDrawerService.php:32`. Cash Flow reads **only account 1000**, no child roll-up (`CashFlowService.php:30,41`). Payroll is the only module that lets you choose the paying account (`PayrollRunService::payPayslip`, `account_id`) | You can create "USD Bank" and "SSP Bank" accounts, but a **supplier payment cannot choose which one it leaves from**: it always credits 1000. Only manual journals and payroll can use them, and the Cash Flow report ignores them. Gap **G1** (blocks go-live) |
| B3 | Cash / petty cash in **USD and SSP** | ❌ Not available | Same as B2 | Same gap: petty cash boxes can only be used through manual journals. Gap **G1** |
| B4 | Opening balances **zero** | ✅ Available | `chart_of_accounts.opening_balance` (migration `2026_11_02_000001`); tenant has no postings | Nothing to enter. Every account starts at 0.00 |
| B5 | Go-live date **01/10/2026** (to confirm) | ✅ Available | Any entry date can be used while its period is open | Configuration only. **But** 01/10/2026 is before the 30 Nov handover: confirm whether October–November transactions will be entered after the fact (see questions) |
| B6 | Historic data: opening balances only | ✅ Available | — | Nothing to import |

### Donors, grants and projects

| ID | Requirement | Status | Evidence | Notes / gap |
|---|---|---|---|---|
| C1 | Donors (2 in pipeline) | ✅ Available | `donors` table, **Grants › Donors** (`DonorsPage.tsx`) | Donor code, name, contact email, notes |
| C2 | Grants: name, donor, amount, currency, start/end, description | ✅ Available | `grants` table (donor, code, name, currency, start/end, budget_amount, status, notes), **Grants › SOFs** (`GrantsListPage.tsx`, `GrantDetailPage.tsx`, `GrantFormDialog.tsx`) | "Description" = the **Notes** field. Grants are called **SOFs** on screen. A grant can have DEAs (sub-codes) and its own budget lines |
| C3 | Projects / programmes | ✅ Available (after enabling M&E) | Projects are `me_projects` (**M&E** menu). Journal lines, supplier bills, PO lines and invoice lines can be coded to a project (`journal_entry_lines.project_id`, migration `2026_10_29_000005`) | **The M&E module is OFF for G711**; until it is switched on nobody can create a project. Configuration only (Platform Admin › Clients › G711 › modules) |
| C4 | Location: Juba, 2 field offices later | ✅ Available | **Settings › Branches** (`branches` with address/phone); branch filter on TB/P&L/BS/GL/Cash Flow (`ReportController`, `branch_id`). Cost centres (**Grants › Cost Centres**) can also code by office | Recommend: one **branch** per office (Juba now, field offices later); reports filter by branch |
| C5 | Budgets per grant/project **uploaded as CSV**; Budget vs Actual | ⚠️ Partial | Budgets: `budget_periods` + `budget_lines` (account, branch, department, SOF, project, cost centre, amount), **Budgeting** menu (`BudgetPeriodDetailPage.tsx`, exports CSV). Budget vs Actual: `GET budget-periods/{id}/vs-actual`, `BudgetVsActualService` (filters by SOF/project/cost centre). Per-grant: `grant_budget_lines` + **SOF Grant Report** (`GrantReportService`, budget vs spend per account and DEA, in USD and in the grant currency) | **Budget vs Actual: yes**, by SOF, project and cost centre, CSV export. **CSV upload: no** (no import anywhere). **Budgeting module is OFF for G711**: switch it on. Workaround: we key the budget lines from G711's filled template. Gap **G7** |
| C6 | Donor reporting templates | ✅ Available (not needed) | **SOF Grant Report** (`/grants/:id/report`) | Not needed now; the grant report exists if wanted later |
| C7 | **Shared costs** split across grants/projects | ⚠️ Partial | Each **journal line** has its own SOF/DEA/project/cost centre (`JournalEntryLine` fillable; `JournalEntryFormPage.tsx:74-77` uses `CodingFields`). Supplier bills carry **one** coding for the whole bill (`supplier_bills` has the dimension columns at bill level; `SupplierBillService::postBill`, line 175). Payroll posts gross salaries to 5000 with **no coding** (`PayrollRunService`, line 228) | **By amount, manually:** yes, in a journal entry (one line per grant). **By %, or on a bill or payroll:** no. Workaround: record the bill or payroll normally, then a monthly **re-allocation journal** moves the cost from "shared" to each grant by amount. Gap **G5** |

### Suppliers, payments and approvals

| ID | Requirement | Status | Evidence | Notes / gap |
|---|---|---|---|---|
| D1 | Supplier list (name, contact, **bank details**) | ⚠️ Partial | `suppliers`: name, contact_person, phone, email, address, tax_id, payment_terms_days. **Procurement › Suppliers** | **No bank-detail fields** and **no import**. Workaround: enter suppliers by hand; bank details stay outside the system for now. Gaps **G7** (import), **G11** (bank fields) |
| D2 | Amounts owed to/by them: zero | ✅ Available | — | Nothing to enter |
| E1 | Payment flow: **user requests → approval by authorisation level → Finance Officer pays** | ❌ Not available | No payment-request document exists (no route, model or screen). Supplier bills have no approval status (`supplier_bills.status`: unpaid/partially_paid/paid/overdue). Approvals that do exist: **Purchase Order** submit → approve (`PurchaseOrderService::submit/approve`, permission `purchase-orders.approve`) and **Journal Entry** draft → post (`journal-entries.post`), both with the maker-checker rule (`app/Support/MakerChecker.php`: creator can't approve their own when someone else can) | One approval step only, no "request" document, no levels. Workaround: requests go in as **Purchase Orders** (submit → Program Officer approves → Finance raises the bill and pays); non-purchase payments as **draft journals** that the approver posts. Gap **G2** (blocks go-live) |
| E2 | Approval **amount limits** (e.g. > USD 500 needs the Director) | ❌ Not available | No threshold, limit or level anywhere (searched for threshold, approval_limit, approval level) | Approval is by permission only, regardless of amount. Gap **G2** |
| E3 | Who approves journals and budget changes | ⚠️ Partial | Journals: `journal-entries.post` + maker-checker (`JournalEntryPolicy.php:40`). Budgets: `budget-periods.update` / `budget-lines.*` permissions; no approval step | Journals: two-person control works **once self-approval is switched off** (it is ON for G711 today). Budget changes: controlled only by who has the permission; no approval or history beyond the audit log (budget lines themselves are not audited) |

### Users and roles

| ID | Requirement | Status | Evidence | Notes / gap |
|---|---|---|---|---|
| R1 | **Admin**: Oyella Eunice (Executive Director) | ✅ Available | Built-in role `company_admin` (`RoleAndPermissionSeeder.php:149`) | Full access within G711 |
| R2 | **Approver**: Euphemia Sunday (Program Officer) | ⚠️ Partial | Custom roles per company: **Settings › Roles** (`RoleService::create`, `company_id` scoped) | Role can be built (view + approve POs + post journals). Limited by E1/E2: only PO approval and journal posting exist to approve |
| R3 | **Data entry**: Ongee Joseph (Finance Officer) | ✅ Available | Custom role in **Settings › Roles** (start from the built-in `accountant` permissions, `RoleAndPermissionSeeder.php:378`) | Remove `journal-entries.post` if the Approver must post journals |
| R4 | **View only**: Philiph (Bongomin Onyuta) | ✅ Available | Custom role with only `*.view` + `reports.view`. Every write action checks a permission in its policy or controller (checked unguarded routes, e.g. `ChartOfAccountController::seed`, `RfqController::send/quote/convert`, `SupplierPaymentController::store`, `PayrollRunController::payAll` all call `authorize`) | Truly read-only if only view permissions are ticked. Email/job title still to confirm |
| R5 | About **10 users** | ✅ Available | No user limit in code (searched for max_users, user_limit, seat) | — |
| R6 | Create users **without sending invites** | ✅ Available | `UserService::create` sets a password; no email is sent (`StoreUserRequest`: password required) | We set a temporary password; share it when G711 is ready |

### How they will use it

| ID | Requirement | Status | Evidence | Notes / gap |
|---|---|---|---|---|
| U1 | 100–500 transactions/month | ✅ Available | Paginated lists and reports; no volume limit found | — |
| U2 | **Payroll** in FlipERP | ⚠️ Partial | **HR & Payroll**: employees, salary structures (basic + allowances), statutory deduction rules, payroll runs (process → payslips → pay one or pay all, export) (`PayrollRunService`, `routes/api.php:221-231`). Posts Dr 5000 / Cr 2100 + 2200, then pays Dr 2200 / Cr a chosen asset account | Works in **USD only** and posts salaries with **no grant/project coding** (see C7). If any staff are paid in SSP, or salaries must be charged to grants, use a re-allocation journal. Gaps **G5**, **G8** |
| U3 | **Staff cash advances** → retire with receipts | ❌ Not available | No advance, imprest or retirement feature (searched `app`, migrations, `frontend/src`) | Workaround: a "Staff Advances" asset account per currency; issue = journal Dr Staff Advances / Cr Cash; retirement = journal Dr expense (coded to grant) / Cr Staff Advances; receipts kept on paper (journals take **no attachments**). No per-staff outstanding report except the account ledger. Gap **G4** |
| U4a | Balance Sheet | ⚠️ Partial | **Accounting › Balance Sheet** (`BalanceSheetReportPage.tsx`; `as_of`, branch, "Show in" currency) | Works. **CSV download only** (built in the browser, `api/reports.ts::downloadReport`); no Excel or PDF. No grant/project filter (not normally needed). Gap **G3** |
| U4b | Profit & Loss (Income & Expenditure) | ⚠️ Partial | **Accounting › Profit & Loss** (`ProfitAndLossReportPage.tsx`; from/to, branch, SOF/DEA/project/cost centre via `CodingFields`, "Show in" currency) | Works with date and grant/project filters. **CSV download only**; no Excel or PDF. Labels say Revenue/Expenses, not Income/Expenditure. Gap **G3** |
| U4c | Budget vs Actual | ✅ Available (after enabling Budgeting) | **Budgeting › (period) › vs actual** (`BudgetPeriodDetailPage.tsx`, CSV); per grant: **Grants › SOFs › Report** (`GrantReportPage.tsx`, CSV) | Filters by SOF/project/cost centre; **Budgeting module must be switched on** |
| U4d | General Ledger | ✅ Available | **Reports Center › General Ledger** (CSV + PDF via the table toolbar, print with letterhead: `CenterReportPage.tsx`, `DataTable.tsx:265`). Also **Accounting › General Ledger** / **Account Ledger** (date, branch, SOF/project filters, original currency per line) | Exportable from Reports Center. The Accounting › General Ledger page itself has no export |
| U4e | Bank & cash reconciliation | ❌ Not available | No reconciliation feature (only the POS cash-drawer shift reconcile, `CashDrawerController`) | Workaround: Account Ledger for the bank account vs the bank statement, ticked off in Excel. Gap **G4** |
| U4f | Detailed transaction listing | ✅ Available | **Reports Center › General Ledger** ("every journal entry", status filter, CSV/PDF); **Accounting › Journal Entries** list | — |
| U5 | Annual audit: GL + annual reports | ⚠️ Partial | GL: CSV/PDF from Reports Center. Annual reports: BS/P&L/TB have no export (browser print only) | GL is fine. Annual statements have CSV only: they need Excel/PDF (G3) and a year-end close (G9) by 30 Jun 2027 |
| AT | **Audit trail** (who created, approved, changed) | ✅ Available | `app/Models/Concerns/Auditable.php` records created/updated/deleted with old and new values and user, on journal entries, bills, payments, POs, RFQs, grants, chart of accounts, exchange rates, payroll runs, periods and more. Journals also store `created_by` and `posted_by`; POs `approved_by`. **Reports Center › Staff Activity / Audit Log** and `GET audit-logs` | Budget lines and journal lines are not audited on their own (their parent entry is) |
| U6 | Single source for recording, reconciliation, reporting, approvals | ⚠️ Partial | — | Recording and reporting: yes. Reconciliation (G4) and approval workflow (G2) are the missing pieces |
| U7 | **Offline** use | ❌ Not available | No service worker, PWA manifest, IndexedDB or offline queue in `frontend/` (searched package.json, vite.config.ts, src) | FlipERP needs an internet connection for every action. G711 also said field staff do *not* have poor internet. Recommend **not** building offline (weeks of work). Confirm with G711 |
| U8 | **RFQ** enabled for G711 | ✅ Available | Procurement module is **ON** in G711's tenant (`enabled_modules` includes `procurement`). **Procurement › Requests for Quotation** (`/rfqs`; permission `purchase-orders.view`, actions need `purchase-orders.create`: `RfqPolicy::manage`) | No RFQs created yet. Give the right users `purchase-orders.*` permissions |

## Count

| Status | Count | Items |
|---|---|---|
| ✅ Available | 21 | A3, B4, B5, B6, C1, C2, C3, C4, C6, D2, R1, R3, R4, R5, R6, U1, U4c, U4d, U4f, AT, U8 (C3 and U4c only after the M&E and Budgeting modules are switched on) |
| ⚠️ Partial | 14 | A1, A2, A4, B1, C5, C7, D1, E3, R2, U2, U4a, U4b, U5, U6 |
| ❌ Not available | 7 | B2, B3, E1, E2, U3, U4e, U7 |
| ❓ Unclear | 0 | — |

42 rows in total. U4 is split into its six reports (U4a–U4f); AT is the audit-trail check.

## Gaps, fixes and estimates

| Gap | What | Fix | Estimate | When |
|---|---|---|---|---|
| **G1** | Several bank/cash accounts (USD bank, SSP bank, petty cash) | Mark accounts as bank/cash with a currency; let supplier payments, customer payments and payroll choose the account; Cash Flow over all bank/cash accounts; per-account balance in its own currency | **3–4 days** | **Before go-live** |
| **G2** | Payment request with approval levels and amount limits | New "Payment Request" (requester, amount, currency, coding, attachments) → approval steps from a per-company limits table (e.g. ≤ USD 500 Program Officer, > USD 500 Director) → Finance pays (creates bill/payment) | **5–7 days** | **Before go-live** (core problem for G711). Fallback: the PO + draft-journal workaround above |
| **G3** | Excel and PDF export for Balance Sheet, P&L, Trial Balance (CSV already existed) | Excel + PDF (letterhead) alongside CSV on the report pages | **1 day** | **Before go-live** |
| **G4** | Bank & cash reconciliation; staff advances & retirement | Reconciliation: statement balance, tick cleared lines, reconciled report. Advances: advance per staff, retirement against receipts (attachments), outstanding-advances report | Reconciliation **4–5 days**; advances **3–4 days** | Reconciliation by first month-end close (Dec 2026); advances after go-live with the journal workaround |
| **G5** | Shared-cost allocation by % (bills, payroll) | Allocation templates (e.g. Rent: 60% SOF-A / 40% SOF-B) applied to a bill or payroll run; split lines on supplier bills | **3–5 days** | After go-live (re-allocation journal meanwhile) |
| **G6** | Standard NGO chart template | Seeder for an NGO chart (restricted/unrestricted income, programme vs support costs, staff advances, bank per currency) | **0.5–1 day** | Optional: we can build G711's chart by hand (~4 h) |
| **G7** | CSV import (budgets, suppliers, chart) | Upload → preview → validate → save, for budget lines, suppliers and accounts | **2–3 days** | After go-live (we key the first data) |
| **G8** | SSP balances, month-end revaluation, payroll in SSP | Unrealised FX revaluation journal at month-end; payroll currency per run | **2–3 days** | After go-live (ask whether payroll is in SSP first) |
| **G9** | Fiscal year July–June | Company "fiscal year start month"; "This financial year" presets; year-end close to accumulated funds | **2–3 days** | Presets soon; year-end close before 30 Jun 2027 |
| **G10** | Company email, registration and tax numbers on profile and documents | Fields on company + document header | **0.5–1 day** | Before go-live (quick win) |
| **G11** | Supplier bank details | Bank name, account name, account number, currency on supplier | **0.5 day** | With G7 |
| — | Offline | Not recommended (internet is fine for G711). Real offline data entry is a multi-week project | — | Not planned |

## Questions to ask G711

1. **Go-live date.** You wrote 01/10/2026; handover is 30/11/2026. Do the books start on 1 October (so October and November must be entered after the fact), or on the handover date?
2. **Approval limits (E2)** and **who approves journals and budget changes (E3)**.
3. **Chart of accounts.** Do you have your own (or Save the Children's) coding to follow, or shall we build a standard NGO chart?
4. **Payroll.** Are any staff paid in **SSP**? Which deductions apply (PAYE, NSIF, other)? Must salaries be charged to grants, and by what %?
5. **Shared costs (C7).** Which costs are shared, and how are they split: fixed % per grant, or worked out monthly?
6. **Bank and cash.** Names, numbers and currency of each bank account; how many petty cash boxes and who holds each.
7. **Exchange rate.** Which rate do you use for SSP (Bank of South Sudan, your bank's rate, market), and how often should it change (daily, weekly)?
8. **Offline (U7).** You asked for offline use but said internet is fine. Is offline really needed, and for what?
9. **Philiph (Bongomin Onyuta).** Email address and job title.
10. **Organisation profile (A1).** Registered name, address, phone, email, registration and tax numbers, logo file.
11. **Projects and field offices.** List of projects/programmes, and the names of the 2 future field offices.
12. **Existing accounts in your tenant.** A Save the Children user already has admin access to G711's tenant. Should they keep it? (Internal: two Nile Hive accounts there hold super_admin/platform_admin roles; review before handover.)
