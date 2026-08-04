<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Receipt {{ $sale->reference_number }}</title>
    <style>
        @page {
            margin: 10px 10px 14px 10px;
        }

        * {
            box-sizing: border-box;
        }

        body {
            font-family: 'DejaVu Sans Mono', 'DejaVu Sans', monospace;
            font-size: 8pt;
            color: #000000;
            margin: 0;
            padding: 0;
        }

        .center {
            text-align: center;
        }

        .bold {
            font-weight: bold;
        }

        .logo {
            display: block;
            max-width: 100px;
            max-height: 46px;
            width: auto;
            height: auto;
            margin: 0 auto 4px auto;
        }

        .company-name {
            font-size: 11pt;
            font-weight: bold;
            margin: 0 0 2px 0;
        }

        .company-meta {
            font-size: 7pt;
            line-height: 1.4;
            color: #000000;
        }

        .rule {
            border: none;
            border-top: 1px dashed #000000;
            margin: 6px 0;
        }

        .banner {
            font-size: 10pt;
            font-weight: bold;
            text-align: center;
            margin: 6px 0 2px 0;
        }

        .barcode-wrap {
            text-align: center;
            margin: 4px 0 6px 0;
        }

        .qr-wrap {
            text-align: center;
            margin: 6px 0;
        }

        .meta-row {
            font-size: 7.5pt;
            line-height: 1.6;
        }

        table.row-table {
            width: 100%;
            border-collapse: collapse;
        }

        table.row-table td {
            font-size: 7.5pt;
            padding: 1px 0;
        }

        table.row-table td.right {
            text-align: right;
        }

        table.items {
            width: 100%;
            border-collapse: collapse;
        }

        table.items th {
            font-size: 7.5pt;
            font-weight: bold;
            text-align: right;
            border-bottom: 1px dashed #000000;
            padding-bottom: 2px;
        }

        table.items th.item-col {
            text-align: left;
        }

        table.items th.unit-col {
            text-align: center;
        }

        table.items td {
            font-size: 7.5pt;
            padding: 2px 0;
            vertical-align: top;
            text-align: right;
        }

        table.items td.item-col {
            text-align: left;
        }

        table.items td.unit-col {
            text-align: center;
        }

        table.totals {
            width: 100%;
            border-collapse: collapse;
            margin-top: 2px;
        }

        table.totals td {
            font-size: 7.5pt;
            padding: 1px 0;
        }

        table.totals td.value {
            text-align: right;
            white-space: nowrap;
        }

        table.totals tr.grand-total td {
            font-size: 10pt;
            font-weight: bold;
        }

        table.vat {
            width: 100%;
            border-collapse: collapse;
        }

        table.vat th {
            font-size: 7pt;
            font-weight: bold;
            text-align: right;
            border-top: 1px dashed #000000;
            border-bottom: 1px dashed #000000;
            padding: 3px 0;
        }

        table.vat th.label-col {
            text-align: left;
        }

        table.vat td {
            font-size: 7pt;
            text-align: right;
            padding: 2px 0 4px 0;
            border-bottom: 1px dashed #000000;
        }

        table.vat td.label-col {
            text-align: left;
        }

        .footer {
            margin-top: 8px;
            font-size: 7.5pt;
        }

        .footer.center {
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="center">
        @if ($logoPath = $company?->logoFilePath())
            <img src="{{ $logoPath }}" alt="{{ $company->name }}" class="logo">
        @endif
        <p class="company-name">{{ strtoupper($company->name ?? 'Receipt') }}</p>
        <div class="company-meta">
            @if (!empty($branch?->address))
                {{ $branch->address }}<br>
            @endif
            @if (!empty($branch?->phone))
                {{ $branch->phone }}<br>
            @endif
            @if (!empty($company?->tax_id))
                TIN : {{ $company->tax_id }}
            @endif
        </div>
    </div>

    <hr class="rule">

    <div class="banner">CASH SALE / TAX INVOICE</div>
    <div class="center">{{ $sale->reference_number }}</div>

    <div class="barcode-wrap">
        <img src="data:image/png;base64,{{ $barcodePng }}" style="height: 40px;">
    </div>

    <table class="row-table meta-row">
        <tr>
            <td>Date : {{ $sale->created_at?->format('n/j/Y, g:i:s A') }}</td>
            <td class="right">Branch: {{ $branch->name ?? '-' }}</td>
        </tr>
        <tr>
            <td colspan="2">Printed : {{ now()->format('n/j/Y') }}</td>
        </tr>
        <tr>
            <td colspan="2">Currency : {{ $currencyCode }}</td>
        </tr>
    </table>

    <div class="meta-row" style="margin-top: 4px;">
        Customer Name : {{ $sale->customer?->name ?: 'Cash Customer' }}
    </div>

    <hr class="rule">

    <table class="items">
        <thead>
            <tr>
                <th class="item-col">Item</th>
                <th>Qty</th>
                <th class="unit-col">Unit</th>
                <th>Price</th>
                <th>Amount</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($sale->items as $item)
                <tr>
                    <td class="item-col">
                        {{ \Illuminate\Support\Str::limit($item->product?->name ?? 'Product #'.$item->product_id, 20, '') }}
                        @if ($item->variant)
                            ({{ $item->variant->name }})
                        @endif
                    </td>
                    <td>{{ rtrim(rtrim(number_format((float) $item->quantity, 2), '0'), '.') }}</td>
                    <td class="unit-col">PCE</td>
                    <td>{{ number_format((float) $item->unit_price, 2) }}</td>
                    <td>{{ number_format((float) $item->line_total, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals">
        <tr class="grand-total">
            <td>Total :</td>
            <td class="value">{{ $currencyCode }} {{ number_format((float) $sale->total_amount, 2) }}</td>
        </tr>
        @forelse ($sale->payments as $payment)
            <tr>
                <td>{{ $payment->paymentType?->name ?? 'Payment' }}</td>
                <td class="value">{{ $currencyCode }} {{ number_format((float) $payment->amount, 2) }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="2">No payments recorded</td>
            </tr>
        @endforelse
        <tr>
            <td>Change :</td>
            <td class="value">0</td>
        </tr>
        <tr>
            <td>Total Items :</td>
            <td class="value">{{ $sale->items->count() }}</td>
        </tr>
    </table>

    <table class="vat">
        <thead>
            <tr>
                <th class="label-col">VAT</th>
                <th>Amt W/o VAT</th>
                <th>VAT Amt</th>
                <th>Net Amt</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td class="label-col">Standard</td>
                <td>{{ number_format((float) $sale->total_amount - (float) $sale->tax_amount, 2) }}</td>
                <td>{{ number_format((float) $sale->tax_amount, 2) }}</td>
                <td>{{ number_format((float) $sale->total_amount, 2) }}</td>
            </tr>
        </tbody>
    </table>

    <table class="row-table" style="margin-top: 4px;">
        <tr>
            <td>Fiscal Doc No :</td>
            <td class="right bold">{{ $sale->reference_number }}</td>
        </tr>
        <tr>
            <td>Verification Code :</td>
            <td class="right bold">{{ $verificationCode }}</td>
        </tr>
    </table>

    <div class="qr-wrap">
        <img src="{{ $qrDataUri }}" style="width: 80px; height: 80px;">
    </div>

    <div class="footer">Received By : _______________________</div>
    <div class="footer">You Were Served By : {{ $sale->servedBy?->name ?? 'ADMIN' }}</div>
    <div class="footer bold center">Thank You !!! Please Come Again....</div>
    <div class="footer center">Powered by {{ config('app.name') }}</div>
</body>
</html>
