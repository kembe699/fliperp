<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Purchase Order {{ $purchaseOrder->reference_number }}</title>
    @include('pdf.partials.styles')
</head>
<body>
    @include('pdf.partials.document-header', [
        'company' => $company,
        'companyName' => $company->name,
        'branchName' => $branch?->name,
        'branchAddress' => $branch?->address,
        'branchPhone' => $branch?->phone,
        'documentType' => 'Purchase Order',
        'referenceNumber' => $purchaseOrder->reference_number,
        'documentDate' => \Illuminate\Support\Carbon::parse($purchaseOrder->order_date)->format('d M Y'),
        'metaRows' => [
            'Expected Delivery' => $purchaseOrder->expected_delivery_date ? \Illuminate\Support\Carbon::parse($purchaseOrder->expected_delivery_date)->format('d M Y') : '—',
            'Status' => strtoupper(str_replace('_', ' ', $purchaseOrder->status)),
        ],
    ])

    @include('pdf.partials.footer-script', ['companyName' => $company->name])

    <div class="bill-to-box">
        <p class="section-label">Supplier</p>
        <p class="bill-to-name">{{ $supplier?->name ?? 'Supplier #' . $purchaseOrder->supplier_id }}</p>
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
                <th style="width: 46%;">Item</th>
                <th class="num" style="width: 14%;">Qty Ordered</th>
                <th class="num" style="width: 18%;">Unit Cost</th>
                <th class="num" style="width: 22%;">Line Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($purchaseOrder->items as $item)
                <tr>
                    <td>
                        {{ $item->product?->name ?? 'Product #' . $item->product_id }}
                        @if ($item->variant)
                            <br><span style="color:#000000; font-size:8.5pt;">{{ $item->variant->name }}</span>
                        @endif
                    </td>
                    <td class="num">{{ rtrim(rtrim(number_format((float) $item->quantity_ordered, 2), '0'), '.') }}</td>
                    <td class="num">{{ $currencyCode }} {{ number_format((float) $item->unit_cost, 2) }}</td>
                    <td class="num">{{ $currencyCode }} {{ number_format((float) $item->quantity_ordered * (float) $item->unit_cost, 2) }}</td>
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
                    {{ number_format($purchaseOrder->items->sum(fn ($item) => (float) $item->quantity_ordered * (float) $item->unit_cost), 2) }}
                </td>
            </tr>
        </table>
    </div>

    <div style="margin-top: 40px;">
        @if ($purchaseOrder->notes)
            <p class="section-label">Notes</p>
            <p class="footer-notes">{{ $purchaseOrder->notes }}</p>
        @endif
    </div>
</body>
</html>
