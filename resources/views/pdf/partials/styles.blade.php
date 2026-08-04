@php
    $accent = '#1B5FAE';
@endphp
<style>
    @page {
        margin: 175px 48px 90px 48px;
    }

    * {
        box-sizing: border-box;
    }

    body {
        font-family: 'DejaVu Sans', sans-serif;
        font-size: 10.5pt;
        color: #1A1A1A;
        margin: 0;
        padding: 0;
    }

    .header {
        position: fixed;
        top: -165px;
        left: 0;
        right: 0;
        height: 165px;
    }

    .company-logo {
        max-width: 145px;
        max-height: 62px;
        width: auto;
        height: auto;
        display: block;
        margin: 0 0 6px 0;
    }

    .company-name {
        font-size: 16pt;
        font-weight: bold;
        color: {{ $accent }};
        margin: 0 0 2px 0;
    }

    .company-meta {
        font-size: 8.5pt;
        color: #6B7280;
        line-height: 1.5;
    }

    .doc-meta-table {
        width: 100%;
    }

    .doc-title {
        font-size: 15pt;
        font-weight: bold;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: #1A1A1A;
        text-align: right;
        margin: 0 0 6px 0;
    }

    .doc-meta-row {
        text-align: right;
        font-size: 9pt;
        color: #374151;
        line-height: 1.6;
    }

    .doc-meta-label {
        color: #6B7280;
        text-transform: uppercase;
        font-size: 7.5pt;
        letter-spacing: 0.4px;
    }

    .rule {
        border: none;
        border-top: 1px solid {{ $accent }};
        margin: 8px 0 16px 0;
    }

    .rule-thin {
        border: none;
        border-top: 1px solid #D1D5DB;
        margin: 4px 0;
    }

    .section-label {
        text-transform: uppercase;
        font-size: 7.5pt;
        letter-spacing: 0.5px;
        color: #6B7280;
        margin: 0 0 4px 0;
    }

    .bill-to-name {
        font-size: 11pt;
        font-weight: bold;
        color: #1A1A1A;
        margin: 0 0 2px 0;
    }

    .bill-to-meta {
        font-size: 9pt;
        color: #374151;
        line-height: 1.5;
    }

    table.items {
        width: 100%;
        border-collapse: collapse;
        margin-top: 18px;
    }

    table.items thead th {
        text-transform: uppercase;
        font-size: 7.5pt;
        letter-spacing: 0.4px;
        color: #6B7280;
        text-align: left;
        padding: 6px 6px;
        border-bottom: 1px solid #9CA3AF;
        font-weight: bold;
    }

    table.items thead th.num {
        text-align: right;
    }

    table.items tbody td {
        padding: 7px 6px;
        border-bottom: 1px solid #E5E7EB;
        font-size: 9.5pt;
        color: #1A1A1A;
        vertical-align: top;
    }

    table.items tbody td.num {
        text-align: right;
        white-space: nowrap;
    }

    .totals {
        width: 300px;
        margin-left: auto;
        margin-top: 14px;
    }

    .totals table {
        width: 100%;
        border-collapse: collapse;
    }

    .totals td {
        padding: 4px 0;
        font-size: 9.5pt;
        color: #374151;
        white-space: nowrap;
    }

    .totals td.label {
        text-align: left;
    }

    .totals td.value {
        text-align: right;
    }

    .totals tr.total-row td {
        border-top: 1px solid {{ $accent }};
        padding-top: 8px;
        font-size: 13pt;
        font-weight: bold;
        color: {{ $accent }};
    }

    .footer {
        position: fixed;
        bottom: -80px;
        left: 0;
        right: 0;
        height: 80px;
    }

    .footer-notes {
        font-size: 8.5pt;
        color: #6B7280;
        line-height: 1.5;
    }

    .status-badge {
        display: inline-block;
        font-size: 8pt;
        text-transform: uppercase;
        letter-spacing: 0.4px;
        color: #374151;
        border: 1px solid #9CA3AF;
        padding: 2px 8px;
        border-radius: 2px;
    }
</style>
