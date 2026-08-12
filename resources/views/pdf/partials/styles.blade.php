@php
    $accent = '#1B5FAE';
    $ink = '#111827';
    $muted = '#6B7280';
    $rule = '#E5E7EB';
@endphp
<style>
    @page {
        margin: 145px 48px 80px 48px;
    }

    * {
        box-sizing: border-box;
    }

    body {
        font-family: 'Helvetica', 'DejaVu Sans', sans-serif;
        font-size: 10pt;
        color: {{ $ink }};
        margin: 0;
        padding: 0;
    }

    .header {
        position: fixed;
        top: -145px;
        left: -48px;
        right: -48px;
        height: 145px;
    }

    .header-inner {
        padding: 0 48px;
    }

    .company-logo {
        max-width: 150px;
        max-height: 50px;
        width: auto;
        height: auto;
        display: block;
        margin: 0 0 8px 0;
    }

    .company-name {
        font-size: 14pt;
        font-weight: bold;
        color: {{ $ink }};
        margin: 0 0 4px 0;
    }

    .company-meta {
        font-size: 8.5pt;
        color: {{ $muted }};
        line-height: 1.5;
    }

    .doc-title {
        font-size: 15pt;
        font-weight: bold;
        letter-spacing: 0.3px;
        color: {{ $accent }};
        text-align: right;
        margin: 0 0 8px 0;
    }

    .doc-meta-row {
        text-align: right;
        font-size: 9pt;
        color: {{ $ink }};
        line-height: 1.7;
    }

    .doc-meta-label {
        color: {{ $muted }};
        text-transform: uppercase;
        font-size: 7.5pt;
        font-weight: bold;
        letter-spacing: 0.4px;
    }

    .rule {
        border: none;
        border-top: 1px solid {{ $rule }};
        margin: 16px 0 0 0;
    }

    .section-label {
        text-transform: uppercase;
        font-size: 7.5pt;
        font-weight: bold;
        letter-spacing: 0.5px;
        color: {{ $muted }};
        margin: 0 0 6px 0;
    }

    .bill-to-box {
        margin: 28px 0 4px 0;
    }

    .bill-to-name {
        font-size: 11pt;
        font-weight: bold;
        color: {{ $ink }};
        margin: 0 0 3px 0;
    }

    .bill-to-meta {
        font-size: 9pt;
        color: {{ $muted }};
        line-height: 1.5;
    }

    table.items {
        width: 100%;
        border-collapse: collapse;
        margin-top: 20px;
    }

    table.items thead th {
        text-transform: uppercase;
        font-size: 7.5pt;
        font-weight: bold;
        letter-spacing: 0.4px;
        color: {{ $muted }};
        text-align: left;
        padding: 0 8px 8px 8px;
        border-bottom: 1px solid {{ $ink }};
    }

    table.items thead th.num {
        text-align: right;
    }

    table.items tbody td {
        padding: 9px 8px;
        border-bottom: 1px solid {{ $rule }};
        font-size: 9.5pt;
        color: {{ $ink }};
        vertical-align: top;
    }

    table.items tbody td.num {
        text-align: right;
        white-space: nowrap;
    }

    .totals {
        width: 260px;
        margin-left: auto;
        margin-top: 4px;
    }

    .totals table {
        width: 100%;
        border-collapse: collapse;
    }

    .totals td {
        padding: 6px 0;
        font-size: 9.5pt;
        color: {{ $ink }};
        white-space: nowrap;
    }

    .totals td.label {
        text-align: left;
        color: {{ $muted }};
    }

    .totals td.value {
        text-align: right;
    }

    .totals tr.total-row td {
        border-top: 1px solid {{ $ink }};
        padding-top: 10px;
        font-size: 12pt;
        font-weight: bold;
        color: {{ $ink }};
    }

    .footer {
        position: fixed;
        bottom: -70px;
        left: -48px;
        right: -48px;
        height: 70px;
        padding: 0 48px;
    }

    .footer-notes {
        font-size: 8.5pt;
        color: {{ $muted }};
        line-height: 1.6;
    }

    .status-badge {
        display: inline-block;
        font-size: 7.5pt;
        font-weight: bold;
        text-transform: uppercase;
        letter-spacing: 0.4px;
        color: {{ $muted }};
        border: 1px solid {{ $rule }};
        padding: 3px 9px;
        border-radius: 3px;
    }
</style>
