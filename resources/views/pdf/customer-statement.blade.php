<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Statement — {{ $statement['customer_name'] }}</title>
    @include('pdf.partials.styles')
</head>
<body>
    @php
        $typeLabels = [
            'sale' => 'POS Sale',
            'sale_payment' => 'POS Payment',
            'invoice' => 'Invoice',
            'invoice_payment' => 'Invoice Payment',
        ];
    @endphp

    @include('pdf.partials.document-header', [
        'companyName' => $company->name,
        'branchName' => null,
        'branchAddress' => null,
        'branchPhone' => null,
        'documentType' => 'Statement',
        'referenceLabel' => 'Customer',
        'referenceNumber' => $statement['customer_name'],
        'documentDate' => \Illuminate\Support\Carbon::now()->format('d M Y'),
        'metaRows' => [
            'Closing Balance' => $currencyCode . ' ' . number_format((float) $statement['closing_balance'], 2),
        ],
    ])

    @include('pdf.partials.footer-script', ['companyName' => $company->name])

    <div class="bill-to-box">
        <p class="section-label">Account</p>
        <p class="bill-to-name">{{ $statement['customer_name'] }}</p>
        <div class="bill-to-meta">
            @if ($customer?->address)
                {{ $customer->address }}<br>
            @endif
            @if ($customer?->phone)
                {{ $customer->phone }}
            @endif
            @if ($customer?->phone && $customer?->email)
                &nbsp;&middot;&nbsp;
            @endif
            @if ($customer?->email)
                {{ $customer->email }}
            @endif
        </div>
    </div>

    <table class="items" style="font-size: 8.5pt;">
        <thead>
            <tr>
                <th style="width: 11%;">Date</th>
                <th style="width: 17%;">Type</th>
                <th style="width: 30%;">Reference</th>
                <th class="num" style="width: 14%;">Debit</th>
                <th class="num" style="width: 14%;">Credit</th>
                <th class="num" style="width: 14%;">Balance</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($statement['transactions'] as $line)
                <tr>
                    <td>{{ \Illuminate\Support\Carbon::parse($line['date'])->format('d M Y') }}</td>
                    <td>{{ $typeLabels[$line['type']] ?? ucfirst(str_replace('_', ' ', $line['type'])) }}</td>
                    <td style="word-break: break-all;">{{ $line['reference_number'] ?? '—' }}</td>
                    <td class="num">{{ $line['debit'] > 0 ? $currencyCode . ' ' . number_format($line['debit'], 2) : '—' }}</td>
                    <td class="num">{{ $line['credit'] > 0 ? $currencyCode . ' ' . number_format($line['credit'], 2) : '—' }}</td>
                    <td class="num">{{ $currencyCode }} {{ number_format($line['running_balance'], 2) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" style="text-align: center; color: #000000;">No transactions on record.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="totals">
        <table>
            <tr class="total-row">
                <td class="label">Closing Balance</td>
                <td class="value">{{ $currencyCode }} {{ number_format((float) $statement['closing_balance'], 2) }}</td>
            </tr>
        </table>
    </div>

    <div style="margin-top: 40px;">
        <p class="footer-notes">
            A positive closing balance indicates the amount owed by the customer as of the statement date above.
        </p>
    </div>
</body>
</html>
