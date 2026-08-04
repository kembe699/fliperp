<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Invoice {{ $invoice->reference_number }}</title>
    @include('pdf.partials.styles')
</head>
<body>
    @include('pdf.partials.document-header', [
        'companyName' => $company->name,
        'branchName' => $branch?->name,
        'branchAddress' => $branch?->address,
        'branchPhone' => $branch?->phone,
        'documentType' => 'Invoice',
        'referenceNumber' => $invoice->reference_number,
        'documentDate' => \Illuminate\Support\Carbon::parse($invoice->invoice_date)->format('d M Y'),
        'metaRows' => [
            'Due Date' => \Illuminate\Support\Carbon::parse($invoice->due_date)->format('d M Y'),
            'Status' => strtoupper(str_replace('_', ' ', $invoice->status)),
        ],
    ])

    @include('pdf.partials.footer-script', ['companyName' => $company->name])

    <div>
        <p class="section-label">Bill To</p>
        <p class="bill-to-name">{{ $customer?->name ?? 'Walk-in Customer' }}</p>
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
            @if ($customer?->tax_id)
                <br>Tax ID: {{ $customer->tax_id }}
            @endif
        </div>
    </div>

    <table class="items">
        <thead>
            <tr>
                <th style="width: 42%;">Item</th>
                <th class="num" style="width: 12%;">Qty</th>
                <th class="num" style="width: 15%;">Unit Price</th>
                <th class="num" style="width: 15%;">Discount</th>
                <th class="num" style="width: 16%;">Line Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($invoice->items as $item)
                <tr>
                    <td>
                        {{ $item->product?->name ?? 'Product #' . $item->product_id }}
                        @if ($item->variant)
                            <br><span style="color:#6B7280; font-size:8.5pt;">{{ $item->variant->name }}</span>
                        @endif
                    </td>
                    <td class="num">{{ rtrim(rtrim(number_format((float) $item->quantity, 2), '0'), '.') }}</td>
                    <td class="num">{{ $currencyCode }} {{ number_format((float) $item->unit_price, 2) }}</td>
                    <td class="num">{{ $currencyCode }} {{ number_format((float) $item->discount_amount, 2) }}</td>
                    <td class="num">{{ $currencyCode }} {{ number_format((float) $item->line_total, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="totals">
        <table>
            <tr>
                <td class="label">Subtotal</td>
                <td class="value">{{ $currencyCode }} {{ number_format((float) $invoice->subtotal, 2) }}</td>
            </tr>
            <tr>
                <td class="label">Discount</td>
                <td class="value">{{ $currencyCode }} {{ number_format((float) $invoice->discount_amount, 2) }}</td>
            </tr>
            <tr>
                <td class="label">Tax</td>
                <td class="value">{{ $currencyCode }} {{ number_format((float) $invoice->tax_amount, 2) }}</td>
            </tr>
            <tr class="total-row">
                <td class="label">Total Due</td>
                <td class="value">{{ $currencyCode }} {{ number_format((float) $invoice->total_amount, 2) }}</td>
            </tr>
            @if ((float) $invoice->amount_paid > 0)
                <tr>
                    <td class="label">Amount Paid</td>
                    <td class="value">{{ $currencyCode }} {{ number_format((float) $invoice->amount_paid, 2) }}</td>
                </tr>
                <tr>
                    <td class="label">Balance Due</td>
                    <td class="value">{{ $currencyCode }} {{ number_format((float) $invoice->total_amount - (float) $invoice->amount_paid, 2) }}</td>
                </tr>
            @endif
        </table>
    </div>

    <div style="margin-top: 40px;">
        <p class="footer-notes">
            Payment is due by {{ \Illuminate\Support\Carbon::parse($invoice->due_date)->format('d M Y') }}.
            Please reference invoice {{ $invoice->reference_number }} with your payment.
        </p>
    </div>
</body>
</html>
