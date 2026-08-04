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
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 8pt;
            color: #000000;
            margin: 0;
            padding: 0;
        }

        .center {
            text-align: center;
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

        .meta-row {
            font-size: 7.5pt;
            line-height: 1.6;
        }

        .meta-label {
            color: #000000;
        }

        table.items {
            width: 100%;
            border-collapse: collapse;
        }

        table.items td {
            font-size: 7.5pt;
            padding: 0;
            vertical-align: top;
        }

        .item-row td {
            padding-top: 4px;
        }

        .item-name {
            text-align: left;
        }

        .item-total {
            text-align: right;
            white-space: nowrap;
            font-weight: bold;
        }

        .item-meta {
            font-size: 7pt;
            color: #000000;
            text-align: left;
            padding-top: 1px;
        }

        table.totals {
            width: 100%;
            border-collapse: collapse;
            margin-top: 2px;
        }

        table.totals td {
            font-size: 7.5pt;
            padding: 2px 0;
        }

        table.totals td.label {
            text-align: left;
        }

        table.totals td.value {
            text-align: right;
            white-space: nowrap;
        }

        table.totals tr.grand-total td {
            border-top: 1px solid #000000;
            padding-top: 5px;
            font-size: 10pt;
            font-weight: bold;
        }

        table.payments {
            width: 100%;
            border-collapse: collapse;
        }

        table.payments td {
            font-size: 7.5pt;
            padding: 2px 0;
        }

        table.payments td.value {
            text-align: right;
            white-space: nowrap;
        }

        .footer {
            margin-top: 10px;
            font-size: 7.5pt;
            text-align: center;
            color: #000000;
        }
    </style>
</head>
<body>
    <div class="center">
        @if ($logoPath = $company?->logoFilePath())
            <img src="{{ $logoPath }}" alt="{{ $company->name }}" class="logo">
        @endif
        <p class="company-name">{{ $company->name ?? 'Receipt' }}</p>
        <div class="company-meta">
            @if (!empty($branch?->address))
                {{ $branch->address }}<br>
            @endif
            @if (!empty($branch?->phone))
                {{ $branch->phone }}
            @endif
        </div>
    </div>

    <hr class="rule">

    <div class="meta-row">
        <span class="meta-label">Reference:</span> {{ $sale->reference_number }}<br>
        <span class="meta-label">Date:</span> {{ \Illuminate\Support\Carbon::parse($sale->sale_date)->format('d M Y') }} {{ $sale->created_at?->format('H:i') }}<br>
        <span class="meta-label">Cashier:</span> {{ $sale->servedBy?->name ?? '—' }}
    </div>

    <hr class="rule">

    <table class="items">
        @foreach ($sale->items as $item)
            <tr class="item-row">
                <td class="item-name">
                    {{ $item->product?->name ?? 'Product #'.$item->product_id }}
                    @if ($item->variant)
                        ({{ $item->variant->name }})
                    @endif
                </td>
                <td class="item-total">{{ $currencyCode }} {{ number_format((float) $item->line_total, 2) }}</td>
            </tr>
            <tr>
                <td class="item-meta" colspan="2">
                    {{ rtrim(rtrim(number_format((float) $item->quantity, 2), '0'), '.') }} x {{ $currencyCode }} {{ number_format((float) $item->unit_price, 2) }}
                </td>
            </tr>
        @endforeach
    </table>

    <hr class="rule">

    <table class="totals">
        <tr>
            <td class="label">Subtotal</td>
            <td class="value">{{ $currencyCode }} {{ number_format((float) $sale->subtotal, 2) }}</td>
        </tr>
        @if ((float) $sale->discount_amount > 0)
            <tr>
                <td class="label">Discount</td>
                <td class="value">-{{ $currencyCode }} {{ number_format((float) $sale->discount_amount, 2) }}</td>
            </tr>
        @endif
        @if ((float) $sale->tax_amount > 0)
            <tr>
                <td class="label">Tax</td>
                <td class="value">{{ $currencyCode }} {{ number_format((float) $sale->tax_amount, 2) }}</td>
            </tr>
        @endif
        <tr class="grand-total">
            <td class="label">Total</td>
            <td class="value">{{ $currencyCode }} {{ number_format((float) $sale->total_amount, 2) }}</td>
        </tr>
    </table>

    <hr class="rule">

    <table class="payments">
        @forelse ($sale->payments as $payment)
            <tr>
                <td class="label">{{ $payment->paymentType?->name ?? 'Payment' }}</td>
                <td class="value">{{ $currencyCode }} {{ number_format((float) $payment->amount, 2) }}</td>
            </tr>
        @empty
            <tr>
                <td class="label" colspan="2">No payments recorded</td>
            </tr>
        @endforelse
    </table>

    <hr class="rule">

    <div class="footer">
        Thank you for your business!
    </div>
</body>
</html>
