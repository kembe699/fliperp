<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Quotation {{ $quotation->reference_number }}</title>
    @include('pdf.partials.styles')
</head>
<body>
    @include('pdf.partials.document-header', [
        'companyName' => $company->name,
        'branchName' => $branch?->name,
        'branchAddress' => $branch?->address,
        'branchPhone' => $branch?->phone,
        'documentType' => 'Quotation',
        'referenceNumber' => $quotation->reference_number,
        'documentDate' => \Illuminate\Support\Carbon::parse($quotation->quotation_date)->format('d M Y'),
        'metaRows' => [
            'Valid Until' => $quotation->valid_until ? \Illuminate\Support\Carbon::parse($quotation->valid_until)->format('d M Y') : '—',
            'Status' => strtoupper(str_replace('_', ' ', $quotation->status)),
        ],
    ])

    @include('pdf.partials.footer-script', ['companyName' => $company->name])

    <div>
        <p class="section-label">Prepared For</p>
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
            @foreach ($quotation->items as $item)
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
                <td class="value">{{ $currencyCode }} {{ number_format((float) $quotation->subtotal, 2) }}</td>
            </tr>
            <tr>
                <td class="label">Discount</td>
                <td class="value">{{ $currencyCode }} {{ number_format((float) $quotation->discount_amount, 2) }}</td>
            </tr>
            <tr>
                <td class="label">Tax</td>
                <td class="value">{{ $currencyCode }} {{ number_format((float) $quotation->tax_amount, 2) }}</td>
            </tr>
            <tr class="total-row">
                <td class="label">Total</td>
                <td class="value">{{ $currencyCode }} {{ number_format((float) $quotation->total_amount, 2) }}</td>
            </tr>
        </table>
    </div>

    <div style="margin-top: 40px;">
        @if ($quotation->notes)
            <p class="section-label">Notes</p>
            <p class="footer-notes">{{ $quotation->notes }}</p>
        @endif
        <p class="footer-notes" style="margin-top: 8px;">
            This quotation is valid until
            {{ $quotation->valid_until ? \Illuminate\Support\Carbon::parse($quotation->valid_until)->format('d M Y') : 'further notice' }}.
            Prices are subject to change thereafter.
        </p>
    </div>
</body>
</html>
