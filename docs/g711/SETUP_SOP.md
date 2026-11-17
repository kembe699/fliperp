# Generation 711 (G711): FlipERP setup SOP

**For:** the Nile Hive team setting up G711, and G711 staff once they are live.
**Tenant:** Generation 711, client code `G711`, company id 6, on the ERP droplet (161.35.83.157).
**Handover:** 30 November 2026.
**Read first:** `docs/g711/REQUIREMENTS_CHECK.md`. Steps marked **⛔ GAP** depend on development that isn't built yet. Each one says what to do until it is.

How to read each step:
- **Where:** the menu path in FlipERP (left sidebar › item).
- **What to do:** numbered actions.
- **Input from G711:** ✔ received / ✘ pending.
- **Check:** how to confirm it worked.

---

## Part 1: Setup, from empty tenant to go-live

### Step 0. Clean the tenant and switch on the right modules
**Where:** Platform Admin › Clients › Generation 711 (Nile Hive staff login); then G711's Settings › Company.
**What to do:**
1. In **Platform Admin › Clients › Generation 711 › Modules**, switch **on**: Procurement (already on), Grants (on), Accounting (on), HR & Payroll (on), **Budgeting** and **M&E** (both currently OFF).
2. Switch **off** what G711 won't use, to keep their menu simple: POS & Sales, Inventory, Logistics, CRM, Assets (keep Assets on only if they want a fixed-asset register).
3. Remove the test records found in the tenant: donor **IOM**, SOF **SOF-11 "Farming"**, cost centre **CCJ "WAU Office"**, and the SSP rate of 7,800 dated 2026-11-02 if G711 doesn't confirm it.
4. Review the existing users (a Save the Children admin and two Nile Hive accounts with super_admin/platform_admin roles). Keep or remove them as agreed with G711 and Nile Hive management.

**Input from G711:** module list ✔ (from this SOP); decision on the Save the Children user ✘ pending.
**Check:** the G711 sidebar shows Budgeting, M&E, Grants, Procurement, HR & Payroll, Accounting and Reports Center. **Grants › SOFs**, **Grants › Donors** and **Grants › Cost Centres** are empty.

### Step 1. Organisation profile (A1)
**Where:** Settings › Company; Settings › Branches.
**What to do:**
1. **Settings › Company:** set **Company Name** to G711's registered name, upload the **Company Logo** (JPG/PNG/WEBP), set **Timezone** to **Africa/Juba** (it is UTC today). Leave **Currency** as USD.
2. In the same screen, **untick "Allow users to approve their own records"**. It is ticked today, which lets a user approve or post their own work.
3. **Settings › Branches:** edit the main branch "HQ": name **Juba Office**, add the office **address** and **phone**.
4. ⛔ **GAP G10:** company email, registration number and tax number have no field yet. Keep them on file until G10 is built.

**Input from G711:** registered name, logo, address, phone, email, registration/tax no. ✘ pending.
**Check:** print any PDF (e.g. a Purchase Order): it shows G711's logo and name.

### Step 2. Financial year July–June and accounting periods (A2)
**Where:** Accounting › Accounting Periods › **New Accounting Period**.
**What to do:**
1. There is no "financial year" setting. The year is defined by the periods you create.
2. Create one period per month for the rest of financial year 2026/27, starting at the go-live month:

   | Name | Start Date | End Date |
   |---|---|---|
   | FY2026/27 – Oct 2026 | 2026-10-01 | 2026-10-31 |
   | FY2026/27 – Nov 2026 | 2026-11-01 | 2026-11-30 |
   | … one per month … | | |
   | FY2026/27 – Jun 2027 | 2027-06-01 | 2027-06-30 |

   (If G711 confirms go-live later than 1 October, start at that month.)
3. Tell G711: on reports, **don't use the "This year" shortcut**; it starts on 1 January. Type **1 July** to **30 June** (or the go-live date) as the dates.
4. ⛔ **GAP G9:** there is no year-end close. Before 30 June 2027, G9 must be built, or a manual closing journal agreed with the auditor.

**Input from G711:** go-live date ✘ to confirm (form says 01/10/2026).
**Check:** the periods list shows Oct 2026 → Jun 2027, all **Open**. P&L run for 2026-07-01 to 2027-06-30 opens without error.

### Step 3. Currencies: USD base, SSP, exchange rates (A3, A4)
**Where:** Accounting › Exchange Rates.
**What to do:**
1. The base currency is **USD** (already set; it can't be changed after go-live).
2. Add an SSP rate: **Currency** = SSP, **Rate** = SSP per 1 USD (e.g. 7,800 means 1 USD = 7,800 SSP), **Effective date** = go-live date.
3. Agree the **rate update routine** with G711: who enters it (Admin), from which source, how often (weekly is usually enough; daily if SSP moves fast). Every SSP document uses the latest rate **on or before its date**, so a missing rate blocks posting.
4. Explain to G711 what multi-currency does and doesn't do:
   - SSP bills, payments and journals are converted to USD **when posted**, at the dated rate.
   - Paying an SSP bill at a different rate posts the difference to **5500 Realised FX Gain/Loss** automatically.
   - Any report can be shown in SSP with **"Show in"** (one rate for the whole report).
   - ⛔ **GAP G1/G8:** there is no running SSP balance per account and no month-end revaluation yet.

**Input from G711:** rate source and frequency ✘ pending; go-live rate ✘ pending.
**Check:** **Exchange Rates** lists SSP with the go-live date. A test SSP journal shows the USD equivalent.

### Step 4. Chart of accounts (B1)
**Where:** Accounting › Chart of Accounts.
**What to do:**
1. There is **no NGO template and no import**. Build G711's chart by hand from their list or from the NGO chart agreed with them (about 3–4 hours).
2. **Keep these system accounts with these exact codes.** The modules post to them by code: **1000** Cash and Bank, **1100** Accounts Receivable, **2000** Accounts Payable, **2100** Statutory Deductions Payable, **2200** Net Salaries Payable, **3900** Opening Balance Equity, **5000** Salaries Expense, **5200** General Operating Expenses, **5500** Realised FX Gain/Loss. You may rename them (e.g. 5000 → "Staff Salaries"), but don't change the codes.
3. Rename or deactivate the business accounts G711 won't use: **4000 Sales Revenue** → e.g. "Grant Income"; **1200 Inventory**, **5300 Cost of Goods Sold** and **5400 Cash Short/Over** → set inactive.
4. Add NGO accounts with **Parent Account** for grouping, for example:
   - 4100 Grant income – restricted
   - 4200 Unrestricted income
   - 1300 Staff advances (USD), 1310 Staff advances (SSP)
   - 3100 Accumulated fund
   - 6000+ programme costs, 7000+ support costs (rent, utilities, fuel, communication, office supplies, bank charges)
5. Leave every **Opening Balance** at 0.00 (B4).

**Input from G711:** their own chart, or approval of ours ✘ pending.
**Check:** **Trial Balance** for any date shows every account at zero. The system codes above all exist.

### Step 5. Bank and cash accounts in USD and SSP (B2, B3)
**Where:** Accounting › Chart of Accounts; Settings › Payment Types.
**What to do:**
1. ⛔ **GAP G1 (blocks go-live):** every supplier payment and customer receipt is posted to **account 1000**, and the Cash Flow report reads only 1000.
2. **Until G1 is built:**
   1. Rename **1000** to "USD Bank – [bank name]", the main account used by payments.
   2. Create **1010 SSP Bank**, **1020 Petty Cash USD** and **1030 Petty Cash SSP** as **asset** accounts.
   3. Move money between them only with **journal entries**. SSP payments and petty-cash spending are recorded as journals from those accounts (see Daily use). Payroll can choose its paying account itself.
3. **Settings › Payment Types:** add **Bank transfer** and **Mobile money** next to the existing **Cash**.
4. **After G1 is built:** mark each account as bank/cash with its currency, and pick it on every payment.

**Input from G711:** bank names, account numbers and currencies; number of petty cash boxes and their custodians ✘ pending.
**Check:** the bank and cash accounts appear under Cash and Bank in the chart. A test journal from 1010 SSP Bank shows the SSP amount and its USD equivalent in **General Ledger (By account)**.

### Step 6. Opening balances (zero) and go-live date (B4, B5, B6)
**Where:** no entry needed.
**What to do:**
1. G711 has **no opening balances** and **no history** to import. Enter nothing.
2. Record the agreed go-live date here: ________ (form says 01/10/2026).
3. If go-live is 1 October, G711 must enter October and November transactions before handover. Plan time for this with the Finance Officer.

**Input from G711:** go-live date confirmation ✘ pending.
**Check:** **Balance Sheet** as at the go-live date shows zero everywhere.

### Step 7. Locations: Juba now, 2 field offices later (C4)
**Where:** Settings › Branches; Grants › Cost Centres.
**What to do:**
1. Juba is the main branch (Step 1).
2. When a field office opens, add it in **Settings › Branches** (Name, Code, Address, Phone), and optionally a matching **Cost Centre** if costs are tracked by office within a grant.
3. Every report can then be filtered by **Branch**.

**Input from G711:** field office names ✘ pending (later).
**Check:** the Branch filter on Profit & Loss lists Juba Office.

### Step 8. Donors, grants (SOFs) and projects (C1–C3)
**Where:** Grants › Donors; Grants › SOFs; M&E.
**What to do:**
1. **Grants › Donors:** add each donor (Code, Name, Contact email, Notes). Two are in the pipeline; add them when signed.
2. **Grants › SOFs:** add each grant: **Code** (e.g. the donor's agreement number), **Name**, **Donor**, **SOF Currency** (usually USD), **Period** (start and end), **Total Award**, **Status**, and **Notes** (the description).
3. On the SOF page, add **DEAs (Detailed Expenditure Activities)** if the donor budget has activity lines.
4. **M&E › Projects:** add each project or programme (Name, Description, Start/End, Status). M&E must be switched on (Step 0).
5. **Grants › Cost Centres:** add any extra cost codes G711 uses (e.g. "Support costs", "Shared – Juba").

**Input from G711:** donor and grant details ✘ pending (none active yet); project list ✘ pending.
**Check:** the **Coding** fields on a new journal line list the SOF, DEA, project and cost centre.

### Step 9. Budgets (C5): template, entry, Budget vs Actual
**Where:** Budgeting (organisation budget); Grants › SOFs › (grant) › Budget (per-grant donor budget).
**What to do:**
1. Send G711 the template below. They fill **one row per budget line**, amounts in **USD** for the organisation budget and in the **SOF currency** for grant budgets.
2. ⛔ **GAP G7:** there is no CSV upload yet. Until it's built, we key the lines from their file:
   - Organisation or project budget: **Budgeting › New Budget Period** (e.g. "FY2026/27 budget", 2026-07-01 to 2027-06-30) › **Add Budget Line** (Account, Branch, Department, Coding [SOF / project / cost centre], Budgeted Amount).
   - Grant budget (donor's approved budget): **Grants › SOFs › (grant) › Budget**, one line per Account and DEA.
3. Set the budget period's status to **active**.

```csv
budget_name,start_date,end_date,account_code,account_name,sof_code,dea_code,project,cost_centre_code,branch_code,department,amount,currency,notes
FY2026/27 budget,2026-07-01,2027-06-30,6010,Programme staff salaries,SOF-001,DEA-01,Youth Livelihoods,,MAIN,,24000.00,USD,
FY2026/27 budget,2026-07-01,2027-06-30,7010,Office rent,,,,SHARED,MAIN,,12000.00,USD,Shared cost
```
   Column rules: `account_code` must exist in the chart. `sof_code`, `dea_code`, `project` and `cost_centre_code` are optional and must match codes already in FlipERP. `amount` is a plain number, no commas or symbols. One row per line; no merged cells.

**Input from G711:** budgets per grant/project ✘ pending.
**Check:** **Budgeting › (period) › Budget vs Actual** shows Total Budgeted equal to G711's file total. **Grants › SOFs › Report** shows the donor budget per account and DEA.

### Step 10. Shared cost allocation (C7)
**Where:** Accounting › Journal Entries; Grants › Cost Centres.
**What to do:**
1. ⛔ **GAP G5:** there are no % split rules, and a supplier bill or payroll run can only carry one coding.
2. **Workaround (agree this with G711):**
   1. Create a cost centre **SHARED – Shared costs**.
   2. Record shared bills (rent, utilities) and payroll normally, coded to **SHARED**.
   3. At month-end, post one **re-allocation journal** per shared account. Credit the account coded SHARED, and debit the same account once per grant, coded to that SOF, with the amounts worked out from the agreed %.
3. Write the agreed split here: ________ (e.g. Rent: SOF-001 60% / SOF-002 40%).

**Input from G711:** which costs are shared and the split ✘ pending.
**Check:** after the journal, **Profit & Loss** filtered by **Cost Centre = SHARED** shows zero for the month, and each SOF's Grant Report shows its share.

### Step 11. Suppliers (D1)
**Where:** Procurement › Suppliers.
**What to do:**
1. Send G711 the template below.
2. ⛔ **GAP G7/G11:** there is no import and no bank-detail fields. Enter each supplier by hand (Name, Contact person, Phone, Email, Address, Tax ID, Payment terms). Keep the bank details in G711's file until G11 is built.
3. Opening balances owed to or by suppliers are **zero** (D2): nothing to enter.

```csv
name,contact_person,phone,email,address,tax_id,payment_terms_days,bank_name,bank_account_name,bank_account_number,bank_currency
Juba Office Supplies Ltd,Mary Akol,+211 912 345 678,sales@example.com,"Hai Malakal, Juba",TIN-000123,30,Equity Bank,Juba Office Supplies Ltd,1234567890,USD
```

**Input from G711:** supplier file ✘ pending ("file to come").
**Check:** **Procurement › Suppliers** lists every supplier in the file; numbers match.

### Step 12. Approval workflow and amount limits (E1–E3)
**Where:** Settings › Roles; Settings › Company.
**What to do:**
1. ⛔ **GAP G2 (blocks go-live):** there's no payment-request document, no approval levels and no amount limits.
2. **Until G2 is built, use these flows:**
   - **Purchases:** Finance Officer (or requester) creates a **Purchase Order** and **submits** it. The Approver (Program Officer) **approves** it. Finance records the **Supplier Bill** and the payment.
   - **Other payments** (allowances, bank charges, transfers): Finance Officer creates a **draft Journal Entry**. The Approver (or the ED for large amounts) **posts** it.
   - The creator can't approve or post their own record once self-approval is off (Step 1).
   - The **amount limit is a written rule** until G2: e.g. above USD 500 the ED approves. FlipERP doesn't enforce it yet.
3. Record G711's answers: amount limits ________; journal approver ________; budget-change approver ________.

**Input from G711:** E2 limits ✘ pending; E3 approvers ✘ pending.
**Check:** log in as the Finance Officer, create and submit a PO, and confirm **Approve** isn't available to them. Then log in as the Approver and approve it.

### Step 13. Payroll (U2)
**Where:** HR & Payroll › Departments, Employees, Salary Structures, Statutory Deduction Rules, Payroll Runs.
**What to do:**
1. **Departments:** add G711's departments (e.g. Programmes, Finance & Admin).
2. **Employees:** add each staff member (code, names, department, position, hire date, employment type, bank name and account number).
3. **Salary Structures:** basic salary and allowances per employee, effective date.
4. **Statutory Deduction Rules:** set up the deductions G711 confirms (e.g. PAYE, NSIF).
5. Monthly: **Payroll Runs** › new run › **Process** › check payslips › **Pay** (or **Pay all**), choosing **Paid from** = the bank or cash account and the **Payment method**.
6. Limits:
   - Payroll is in **USD only** (⛔ G8). If any staff are paid in SSP, record those salaries by journal.
   - Salaries post to **5000** with no grant coding (⛔ G5). Charge them to grants with the month-end re-allocation journal (Step 10).

**Input from G711:** staff list, salaries, deductions, payroll currency ✘ pending.
**Check:** a test run posts Dr 5000 / Cr 2100 + 2200; paying it posts Dr 2200 / Cr the chosen bank account. **Reports Center › Payroll Summary** matches.

### Step 14. Staff cash advances and retirement (U3)
**Where:** Accounting › Chart of Accounts; Accounting › Journal Entries.
**What to do:**
1. ⛔ **GAP G4:** there's no advance feature. Use the accounts **1300 Staff advances (USD)** and **1310 Staff advances (SSP)** from Step 4.
2. **Issue an advance:** journal Dr 1300/1310 (description: staff name and purpose) / Cr the cash or bank account.
3. **Retire it:** journal Dr the expense accounts, **coded to the SOF/project** / Cr 1300/1310. Return any unspent cash: Dr cash / Cr 1300/1310.
4. Receipts stay on paper; write the journal reference on them. Journals take no attachments.
5. Outstanding advances: **Accounting › General Ledger › By account** for 1300/1310.

**Input from G711:** advance policy (limits, days to retire) ✘ pending.
**Check:** after issue and full retirement, the 1300 balance for that advance is zero.

### Step 15. Users and roles (create, do not send invites)
**Where:** Settings › Roles; Settings › Users.
**What to do:**
1. **Settings › Roles:** create three company roles:
   - **G711 Approver:**
     - `*.view` on the modules G711 uses (purchase orders, supplier bills, supplier payments, journal entries, grants, budget periods)
     - `purchase-orders.approve`, `journal-entries.post`
     - `reports.view`, `dashboard.view`
   - **G711 Data entry:**
     - start from the built-in **accountant** permissions, and remove `journal-entries.post`, `accounting-periods.close`, `chart-of-accounts.delete`
     - add `purchase-orders.view/create/update/submit`, `suppliers.*`
     - add `payroll-runs.view/create/process` (`process` also allows paying salaries)
   - **G711 View only:** only `*.view` permissions, `reports.view` and `dashboard.view`. Nothing else.
2. **Settings › Users:** create each user with a **temporary password** (8+ characters, mixed case and a number). FlipERP sends **no email**; share passwords only when G711 says so.

   | Name | Email | Role |
   |---|---|---|
   | Oyella Eunice (Executive Director) | info.generation711@gmail.com | **company_admin** |
   | Euphemia Sunday (Program Officer) | euphemia.generation711@gmail.com | **G711 Approver** |
   | Ongee Joseph (Finance Officer) | ongeejos@gmail.com | **G711 Data entry** |
   | Philiph (Bongomin Onyuta) | ✘ pending | **G711 View only** |

3. Room for about 10 users: there's no user limit.

**Input from G711:** Philiph's email and job title ✘ pending; other three ✔ received.
**Check:** log in as each user:
- **View only** sees reports but no **New** / **Edit** / **Post** buttons, and any write attempt is refused.
- **Data entry** can't post journals.
- **Approver** can approve POs and post journals.

### Step 16. RFQ / procurement check (U8)
**Where:** Procurement › Requests for Quotation.
**What to do:**
1. Confirm the menu item is visible (Procurement is **on** in G711's tenant).
2. Users who create RFQs need `purchase-orders.create`. Viewing needs `purchase-orders.view`.
3. Run one test: new RFQ › add supplier and items › **Send** › record a **Quote** › **Convert** to a Purchase Order (it is submitted for approval) › delete or cancel the test afterwards.

**Input from G711:** none.
**Check:** the test RFQ converts to a submitted PO, and the Approver can approve it.

### Step 17. Reports check (U4, U5)
**Where:** Accounting (report pages); Reports Center; Budgeting; Grants.
**What to do:** post a few test transactions in a test period, then run each report and confirm it works and exports:

| Report | Where | Filters to test | Export |
|---|---|---|---|
| Balance Sheet | Accounting › Balance Sheet | As at, Branch, Show in SSP | ⛔ G3: browser **Print → Save as PDF** only |
| Profit & Loss (I&E) | Accounting › Profit & Loss | From/To (1 Jul–30 Jun), Branch, SOF/project | ⛔ G3: Print → Save as PDF |
| Budget vs Actual | Budgeting › (period); Grants › SOFs › Report | SOF, project, cost centre | CSV |
| General Ledger | Reports Center › General Ledger | From/To, status | CSV, PDF, Print |
| Bank & cash reconciliation | — | — | ⛔ G4: General Ledger (By account) vs bank statement in Excel |
| Transaction listing | Reports Center › General Ledger ("Every journal entry"); Accounting › Journal Entries | From/To, status | CSV, PDF |
| Audit trail | Reports Center › Staff Activity / Audit Log | By user, module, action | CSV, PDF |

Afterwards, reverse or delete the test entries, and make sure the test period is clean before go-live.
**Input from G711:** none.
**Check:** each report opens with the right figures; each export file opens in Excel or a PDF reader.

### Step 18. Final go-live checklist
- [ ] Modules: Budgeting and M&E on; unused modules off (Step 0)
- [ ] Test data removed; user list reviewed (Step 0)
- [ ] Company name, logo, timezone Africa/Juba; **self-approval off** (Step 1)
- [ ] Monthly periods created from go-live to June 2027 (Step 2)
- [ ] SSP rate entered for the go-live date; rate routine agreed (Step 3)
- [ ] Chart of accounts built; system codes kept (Step 4)
- [ ] Bank and cash accounts created; **G1 delivered** or workaround agreed in writing (Step 5)
- [ ] Go-live date confirmed (Step 6)
- [ ] Branch, donors, SOFs, DEAs, projects, cost centres entered (Steps 7–8)
- [ ] Budgets entered and Budget vs Actual totals match G711's file (Step 9)
- [ ] Shared-cost split agreed (Step 10)
- [ ] Suppliers entered (Step 11)
- [ ] Approval flow agreed; **G2 delivered** or workaround agreed in writing; limits recorded (Step 12)
- [ ] Payroll set up and a test run checked (Step 13)
- [ ] Advance procedure agreed (Step 14)
- [ ] 4 users created with the right roles; access tested (Step 15)
- [ ] RFQ test done and cleaned up (Step 16)
- [ ] Reports checked; **G3 delivered** (Step 17)
- [ ] Test transactions removed; Trial Balance all zero at go-live
- [ ] Training session held for each role (Part 2)
- [ ] Handover signed by G711

---

## Part 2: Daily use for G711 staff

### Finance Officer (Data entry)

**Record a purchase request (until the Payment Request feature, G2, arrives)**
1. **Procurement › Purchase Orders › New:** supplier, items and amounts, currency, **Coding** (SOF / project / cost centre) on each line.
2. **Submit.** The Approver receives it for approval.

**Record a supplier bill**
1. **Procurement › Supplier Bills › New:** supplier, reference, bill date, due date, currency (USD or SSP), amount, expense account, **Coding**.
2. Save. The bill posts Dr expense / Cr Accounts Payable.

**Pay a supplier**
1. Open the bill › **Record Payment**: payment date, amount, payment type, reference.
2. ⛔ Until G1, this always comes out of account 1000 (USD Bank). For an SSP payment or a petty-cash payment, use a journal instead (below).

**Record money received (donor funds)**
1. **Accounting › Journal Entries › New:** Dr bank account / Cr grant income (4100), coded to the SOF.
2. Leave it as a **draft** for the Approver to post.

**Petty cash spending, and SSP bank payments**
1. **Journal Entries › New:** currency SSP (or USD); Dr expense account coded to the SOF/project / Cr Petty Cash (or SSP Bank).
2. Leave it as a draft for the Approver to post.

**Staff advance and retirement**
- Follow Step 14 (journal Dr Staff advances / Cr cash; retirement journal Dr expenses coded / Cr Staff advances).

**Split a shared cost**
- At month-end, post the re-allocation journal from Step 10: one debit line per SOF, each with its amount and **Coding**, and one credit line coded SHARED.

### Program Officer (Approver)
1. **Procurement › Purchase Orders:** filter status **Submitted** › open each › check amount, supplier, coding and budget › **Approve** or **Cancel**, with a note.
2. **Accounting › Journal Entries:** filter **Draft** › open › check lines, coding and supporting papers › **Post**. If it's wrong, ask the Finance Officer to correct it; don't post it.
3. Follow G711's written amount limits (e.g. above USD 500, pass it to the ED). ⛔ FlipERP won't enforce limits until G2.
4. Check spending against budget in **Budgeting › Budget vs Actual** before approving large items.

### Executive Director (Admin)
1. **Users:** **Settings › Users:** add, deactivate and reset passwords. **Settings › Roles:** adjust permissions.
2. **Approval limits:** keep the written limits up to date until G2 is built, then maintain them in FlipERP.
3. **Exchange rates:** **Accounting › Exchange Rates › New:** SSP rate and its effective date, on the agreed routine (e.g. every Monday).
4. **Month-end close:** run the month-end routine below, then **Accounting › Accounting Periods › (month) › Close**. Drafts must be posted or deleted first; after closing, nothing can be posted into that month.

### View only (e.g. Philiph)
| To see | Go to |
|---|---|
| Balance Sheet | Accounting › Balance Sheet |
| Income & Expenditure (P&L) | Accounting › Profit & Loss (type 1 July as the start date) |
| Budget vs Actual | Budgeting › (budget) ; Grants › SOFs › (grant) › Report |
| General Ledger / transaction listing | Reports Center › General Ledger |
| One account's movements (e.g. a bank) | Accounting › General Ledger › By account |
| Who did what | Reports Center › Staff Activity / Audit Log |

---

## Part 3: Month-end routine (Finance Officer prepares, ED closes)

1. **Post or delete every draft** for the month: **Journal Entries**, filter Draft.
2. **Exchange rate:** make sure an SSP rate exists for the last day of the month (**Accounting › Exchange Rates**).
3. **Bank and cash reconciliation** (⛔ G4, manual until built):
   1. **Accounting › General Ledger › By account** for each bank and cash account, for the month.
   2. Compare it line by line with the bank statement or the cash count; list the differences (timing items, bank charges, errors).
   3. Post journals for bank charges and corrections.
   4. Keep the signed reconciliation (Excel/PDF) on file.
4. **Shared costs:** post the re-allocation journal (Step 10). Also post the payroll re-allocation if salaries are shared.
5. **Staff advances:** **General Ledger (By account)** for 1300/1310. Chase anything still open past the policy deadline.
6. **Budget vs Actual:** **Budgeting › (budget) › Budget vs Actual**, and **Grants › SOFs › Report** for each grant. Explain big variances to the ED.
7. **Export for the file:**
   - **Reports Center › General Ledger** for the month: CSV and PDF.
   - **Balance Sheet** and **Profit & Loss**: Print → Save as PDF (⛔ G3).
8. **Close the period:** **Accounting › Accounting Periods › (month) › Close** (ED).
