<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Goods Received Note {{ $grn->reference_number }}</title>
    @include('pdf.partials.styles')
</head>
<body>
    @include('pdf.partials.document-header', [
        'company' => $company,
        'companyName' => $company->name,
        'branchName' => $warehouse?->name,
        'documentType' => 'Goods Received Note',
        'referenceNumber' => $grn->reference_number,
        'documentDate' => \Illuminate\Support\Carbon::parse($grn->received_date)->format('d M Y'),
        'metaRows' => [
            'Purchase Order' => $grn->purchaseOrder?->reference_number ?? '—',
            'Status' => strtoupper($grn->status),
            'Received By' => $grn->receivedBy?->name ?? '—',
        ],
    ])

    @include('pdf.partials.footer-script', ['companyName' => $company->name])

    <div>
        <p class="section-label">Supplier</p>
        <p class="bill-to-name">{{ $supplier?->name ?? 'Supplier #' . $grn->supplier_id }}</p>
        <div class="bill-to-meta">
            @if ($supplier?->address)
                {{ $supplier->address }}<br>
            @endif
            @if ($supplier?->phone)
                {{ $supplier->phone }}
            @endif
            @if ($supplier?->phone && $supplier?->email)
                &nbsp;&middot;&nbsp;
            @endif
            @if ($supplier?->email)
                {{ $supplier->email }}
            @endif
        </div>
    </div>

    <table class="items">
        <thead>
            <tr>
                <th style="width: 40%;">Item</th>
                <th class="num" style="width: 14%;">Qty Received</th>
                <th class="num" style="width: 16%;">Unit Cost</th>
                <th class="num" style="width: 16%;">Line Total</th>
                <th style="width: 14%;">Condition</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($grn->items as $item)
                <tr>
                    <td>
                        {{ $item->product?->name ?? 'Product #' . $item->product_id }}
                        @if ($item->variant)
                            <br><span style="color:#6B7280; font-size:8.5pt;">{{ $item->variant->name }}</span>
                        @endif
                    </td>
                    <td class="num">{{ rtrim(rtrim(number_format((float) $item->quantity_received, 2), '0'), '.') }}</td>
                    <td class="num">{{ $currencyCode }} {{ number_format((float) $item->unit_cost, 2) }}</td>
                    <td class="num">{{ $currencyCode }} {{ number_format((float) $item->quantity_received * (float) $item->unit_cost, 2) }}</td>
                    <td style="text-transform: capitalize;">{{ $item->condition }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="totals">
        <table>
            <tr class="total-row">
                <td class="label">Total</td>
                <td class="value">
                    {{ $currencyCode }}
                    {{ number_format($grn->items->sum(fn ($item) => (float) $item->quantity_received * (float) $item->unit_cost), 2) }}
                </td>
            </tr>
        </table>
    </div>

    <div style="margin-top: 40px;">
        @if ($grn->notes)
            <p class="section-label">Notes</p>
            <p class="footer-notes">{{ $grn->notes }}</p>
        @endif
    </div>
</body>
</html>
