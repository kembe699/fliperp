<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Request for Quotation {{ $rfq->reference_number }}</title>
    @include('pdf.partials.styles')
</head>
<body>
    @include('pdf.partials.document-header', [
        'company' => $company,
        'companyName' => $company->name,
        'branchName' => $branch?->name,
        'branchAddress' => $branch?->address,
        'branchPhone' => $branch?->phone,
        'documentType' => 'Request for Quotation',
        'referenceNumber' => $rfq->reference_number,
        'documentDate' => \Illuminate\Support\Carbon::parse($rfq->issue_date)->format('d M Y'),
        'metaRows' => [
            'Quote by' => $rfq->response_due_date ? \Illuminate\Support\Carbon::parse($rfq->response_due_date)->format('d M Y') : '—',
            'Deliver to' => $rfq->warehouse?->name ?? $branch?->name ?? '—',
        ],
    ])

    @include('pdf.partials.footer-script', ['companyName' => $company->name])

    <div class="bill-to-box">
        <p class="section-label">To</p>
        @if ($supplier)
            <p class="bill-to-name">{{ $supplier->name }}</p>
            <div class="bill-to-meta">
                @if ($supplier->address){{ $supplier->address }}<br>@endif
                @if ($supplier->phone){{ $supplier->phone }}@endif
                @if ($supplier->phone && $supplier->email)&nbsp;&middot;&nbsp;@endif
                @if ($supplier->email){{ $supplier->email }}@endif
            </div>
        @else
            <p class="bill-to-name">Prospective suppliers</p>
        @endif
    </div>

    <p style="margin: 0 0 10px; font-weight: bold;">{{ $rfq->title }}</p>
    <p style="margin: 0 0 14px;">
        Please quote your best unit price and delivery time for the items below
        @if ($rfq->response_due_date) by {{ \Illuminate\Support\Carbon::parse($rfq->response_due_date)->format('d M Y') }}@endif.
        Prices in {{ $currencyCode }}.
    </p>

    <table class="items">
        <thead>
            <tr>
                <th style="width: 5%;">#</th>
                <th style="width: 41%;">Item</th>
                <th class="num" style="width: 14%;">Quantity</th>
                <th class="num" style="width: 20%;">Unit Price</th>
                <th class="num" style="width: 20%;">Line Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($rfq->items as $i => $item)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>
                        {{ $item->product?->name ?? 'Product #' . $item->product_id }}
                        @if ($item->variant)<br><span style="font-size:8.5pt;">{{ $item->variant->name }}</span>@endif
                        @if ($item->description)<br><span style="font-size:8.5pt;">{{ $item->description }}</span>@endif
                    </td>
                    <td class="num">{{ rtrim(rtrim(number_format((float) $item->quantity, 2), '0'), '.') }}</td>
                    <td class="num">&nbsp;</td>
                    <td class="num">&nbsp;</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table style="width: 100%; margin-top: 18px;">
        <tr>
            <td style="width: 50%; padding: 6px 0;">Delivery time (days): ____________________</td>
            <td style="width: 50%; padding: 6px 0;">Quote valid until: ____________________</td>
        </tr>
        <tr>
            <td style="padding: 6px 0;">Supplier signature: ____________________</td>
            <td style="padding: 6px 0;">Date: ____________________</td>
        </tr>
    </table>

    <div style="margin-top: 30px;">
        @if ($rfq->notes)
            <p class="section-label">Notes</p>
            <p class="footer-notes">{{ $rfq->notes }}</p>
        @endif
    </div>
</body>
</html>
