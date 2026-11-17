{{-- Payment request voucher, with its full approval trail, for the audit file. --}}
@php
    $fmt = fn ($v) => number_format((float) $v, 2);
    $date = fn ($d) => $d ? \Illuminate\Support\Carbon::parse($d)->timezone($company?->timezone ?: config('app.timezone'))->format('d M Y H:i') : '—';
    $statusLabel = ucwords(str_replace('_', ' ', $request->status));
    $coding = array_filter([
        'SOF' => $request->grant?->code,
        'DEA' => $request->dea?->code,
        'Project' => $request->project?->name,
        'Cost centre' => $request->costCentre?->code,
    ]);
    $links = array_filter([
        'Purchase order' => $request->purchaseOrder?->reference_number,
        'RFQ' => $request->rfq?->reference_number,
        'Supplier bill' => $request->supplierBill?->reference_number,
    ]);
    $rounds = $request->approvals->sortBy(['round', 'sequence'])->groupBy('round');
@endphp
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Payment Request {{ $request->reference_number }}</title>
    @include('pdf.partials.styles')
    <style>
        table.kv { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
        table.kv td { padding: 4px 6px; border-bottom: 1px solid #E5E7EB; vertical-align: top; font-size: 9pt; }
        table.kv td.k { width: 28%; color: #6B7280; text-transform: uppercase; font-size: 7.5pt; font-weight: bold; letter-spacing: 0.3px; }
        table.trail { width: 100%; border-collapse: collapse; font-size: 8.5pt; }
        table.trail th { text-align: left; border-bottom: 1px solid #111827; padding: 4px 6px; font-size: 7.5pt; text-transform: uppercase; color: #6B7280; }
        table.trail td { padding: 4px 6px; border-bottom: 1px solid #E5E7EB; vertical-align: top; }
        .amount-box { border: 1.5px solid #111827; padding: 10px 12px; margin: 6px 0 16px; }
        .amount-box .big { font-size: 15pt; font-weight: bold; }
        .muted { color: #6B7280; }
        .sign td { padding: 18px 6px 4px; font-size: 9pt; }
    </style>
</head>
<body>
    @include('pdf.partials.document-header', [
        'company' => $company,
        'companyName' => $company?->name,
        'branchName' => $branch?->name,
        'branchAddress' => $branch?->address,
        'branchPhone' => $branch?->phone,
        'documentType' => 'Payment Request',
        'referenceNumber' => $request->reference_number,
        'documentDate' => \Illuminate\Support\Carbon::parse($request->request_date)->format('d M Y'),
        'metaRows' => ['Status' => $statusLabel, 'Requested by' => $request->requester?->name ?? '—'],
    ])
    @include('pdf.partials.footer-script', ['companyName' => $company?->name])

    <div class="amount-box">
        <span class="muted">Pay</span> <strong>{{ $request->payeeLabel() }}</strong><br>
        <span class="big">{{ $request->currency_code }} {{ $fmt($request->amount) }}</span>
        @if ($request->currency_code !== $baseCurrency)
            <span class="muted">&nbsp;= {{ $baseCurrency }} {{ $fmt($request->base_amount) }} at {{ rtrim(rtrim(number_format((float) $request->exchange_rate, 4), '0'), '.') }} {{ $request->currency_code }}/{{ $baseCurrency }}</span>
        @endif
    </div>

    <table class="kv">
        <tr><td class="k">Purpose</td><td>{{ $request->description }}</td></tr>
        <tr><td class="k">Expense account</td><td>{{ $request->expenseAccount ? $request->expenseAccount->code.' '.$request->expenseAccount->name : ($request->supplierBill ? 'Settles the supplier bill (Accounts Payable)' : '5200 General Operating Expenses (default)') }}</td></tr>
        @foreach ($coding as $label => $value)
            <tr><td class="k">{{ $label }}</td><td>{{ $value }}</td></tr>
        @endforeach
        @foreach ($links as $label => $value)
            <tr><td class="k">{{ $label }}</td><td>{{ $value }}</td></tr>
        @endforeach
        @if ($request->attachments->isNotEmpty())
            <tr><td class="k">Supporting documents</td><td>{{ $request->attachments->pluck('file_name')->implode(', ') }}</td></tr>
        @endif
    </table>

    <p class="section-label">Approvals</p>
    @forelse ($rounds as $round => $steps)
        @if ($rounds->count() > 1)
            <p class="muted" style="margin: 6px 0 2px; font-size: 8pt;">Submission {{ $round }}</p>
        @endif
        <table class="trail">
            <thead>
                <tr><th style="width:6%">#</th><th style="width:24%">Step</th><th style="width:14%">Decision</th><th style="width:22%">By</th><th style="width:16%">When</th><th>Comment</th></tr>
            </thead>
            <tbody>
                @foreach ($steps as $step)
                    <tr>
                        <td>{{ $step->sequence }}</td>
                        <td>{{ $step->label }}</td>
                        <td>{{ ucfirst($step->status) }}</td>
                        <td>{{ $step->decider?->name ?? '—' }}</td>
                        <td>{{ $step->decided_at ? $date($step->decided_at) : '—' }}</td>
                        <td>{{ $step->comment }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @empty
        <p class="muted">Not yet submitted for approval.</p>
    @endforelse

    <p class="section-label" style="margin-top: 14px;">Payment</p>
    @if ($request->status === 'paid')
        <table class="kv">
            <tr><td class="k">Paid on</td><td>{{ \Illuminate\Support\Carbon::parse($request->payment_date)->format('d M Y') }} by {{ $request->payer?->name }}</td></tr>
            <tr><td class="k">Paid from</td><td>{{ $request->paymentAccount ? $request->paymentAccount->code.' '.$request->paymentAccount->name : '—' }}{{ $request->paymentType ? ' · '.$request->paymentType->name : '' }}</td></tr>
            <tr><td class="k">Payment reference</td><td>{{ $request->payment_reference ?: '—' }}</td></tr>
            <tr><td class="k">Journal entry</td><td>{{ $request->journalEntry?->reference_number ?? '—' }}</td></tr>
        </table>
    @else
        <p class="muted">Not paid.</p>
    @endif

    <p class="section-label" style="margin-top: 14px;">History</p>
    <table class="trail">
        <thead><tr><th style="width:20%">When</th><th style="width:22%">Who</th><th style="width:18%">Action</th><th>Comment</th></tr></thead>
        <tbody>
            @foreach ($request->events as $event)
                <tr>
                    <td>{{ $date($event->created_at) }}</td>
                    <td>{{ $event->user?->name ?? '—' }}</td>
                    <td>{{ ucfirst(str_replace('_', ' ', $event->action)) }}</td>
                    <td>{{ $event->comment }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="sign" style="width: 100%; margin-top: 18px;">
        <tr>
            <td style="width: 50%;">Received by (payee): ____________________</td>
            <td style="width: 50%;">Date: ____________________</td>
        </tr>
    </table>
</body>
</html>
