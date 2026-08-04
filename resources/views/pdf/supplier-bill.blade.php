<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Supplier Bill {{ $bill->reference_number }}</title>
    @include('pdf.partials.styles')
</head>
<body>
    @include('pdf.partials.document-header', [
        'company' => $company,
        'companyName' => $company->name,
        'documentType' => 'Supplier Bill',
        'referenceNumber' => $bill->reference_number,
        'documentDate' => \Illuminate\Support\Carbon::parse($bill->bill_date)->format('d M Y'),
        'metaRows' => [
            'Due Date' => \Illuminate\Support\Carbon::parse($bill->due_date)->format('d M Y'),
            'Status' => strtoupper(str_replace('_', ' ', $bill->status)),
        ],
    ])

    @include('pdf.partials.footer-script', ['companyName' => $company->name])

    <div>
        <p class="section-label">Supplier</p>
        <p class="bill-to-name">{{ $supplier?->name ?? 'Supplier #' . $bill->supplier_id }}</p>
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

    @if ($bill->goodsReceivedNote || $bill->purchaseOrder)
        <div style="margin-top: 12px;">
            <p class="footer-notes">
                @if ($bill->goodsReceivedNote)
                    Linked GRN: {{ $bill->goodsReceivedNote->reference_number }}
                @endif
                @if ($bill->goodsReceivedNote && $bill->purchaseOrder)
                    &nbsp;&middot;&nbsp;
                @endif
                @if ($bill->purchaseOrder)
                    Linked PO: {{ $bill->purchaseOrder->reference_number }}
                @endif
            </p>
        </div>
    @endif

    {{--
        supplier_bills has no line-items table in the schema — subtotal is a
        single stored figure whether entered manually or rolled up from a
        GRN at creation time — so there's no per-line breakdown to render
        here, only the totals summary below.
    --}}
    <div class="totals" style="margin-top: 24px;">
        <table>
            <tr>
                <td class="label">Subtotal</td>
                <td class="value">{{ $currencyCode }} {{ number_format((float) $bill->subtotal, 2) }}</td>
            </tr>
            <tr>
                <td class="label">Tax</td>
                <td class="value">{{ $currencyCode }} {{ number_format((float) $bill->tax_amount, 2) }}</td>
            </tr>
            <tr class="total-row">
                <td class="label">Total</td>
                <td class="value">{{ $currencyCode }} {{ number_format((float) $bill->total_amount, 2) }}</td>
            </tr>
            <tr>
                <td class="label">Amount Paid</td>
                <td class="value">{{ $currencyCode }} {{ number_format((float) $bill->amount_paid, 2) }}</td>
            </tr>
            <tr>
                <td class="label">Balance Due</td>
                <td class="value">{{ $currencyCode }} {{ number_format((float) $bill->total_amount - (float) $bill->amount_paid, 2) }}</td>
            </tr>
        </table>
    </div>
</body>
</html>
