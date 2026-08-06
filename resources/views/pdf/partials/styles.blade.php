@php
    $accent = '#1B5FAE';
    $accentDark = '#123F73';
    $accentTint = '#EAF1FA';
    $ink = '#000000';
@endphp
<style>
    @page {
        margin: 185px 48px 95px 48px;
    }

    * {
        box-sizing: border-box;
    }

    body {
        font-family: 'Helvetica', 'DejaVu Sans', sans-serif;
        font-size: 10.5pt;
        color: {{ $ink }};
        margin: 0;
        padding: 0;
    }

    .header {
        position: fixed;
        top: -175px;
        left: -48px;
        right: -48px;
        height: 175px;
    }

    .header-band {
        height: 6px;
        background-color: {{ $accent }};
        margin-bottom: 22px;
    }

    .header-inner {
        padding: 0 48px;
    }

    .company-logo {
        max-width: 155px;
        max-height: 60px;
        width: auto;
        height: auto;
        display: block;
        margin: 0 0 8px 0;
    }

    .company-name {
        font-size: 17pt;
        font-weight: bold;
        color: {{ $ink }};
        margin: 0 0 3px 0;
        letter-spacing: 0.2px;
    }

    .company-meta {
        font-size: 8.5pt;
        color: {{ $ink }};
        line-height: 1.6;
    }

    .doc-meta-table {
        width: 100%;
    }

    .doc-title {
        font-size: 19pt;
        font-weight: bold;
        text-transform: uppercase;
        letter-spacing: 1px;
        color: {{ $accent }};
        text-align: right;
        margin: 0 0 10px 0;
    }

    .doc-meta-row {
        text-align: right;
        font-size: 9pt;
        color: {{ $ink }};
        line-height: 1.7;
    }

    .doc-meta-label {
        color: {{ $ink }};
        text-transform: uppercase;
        font-size: 7.5pt;
        font-weight: bold;
        letter-spacing: 0.5px;
    }

    .rule {
        border: none;
        border-top: 2px solid {{ $accent }};
        margin: 14px 48px 18px 48px;
    }

    .rule-thin {
        border: none;
        border-top: 1px solid {{ $accent }};
        margin: 0;
    }

    .section-label {
        text-transform: uppercase;
        font-size: 7.5pt;
        font-weight: bold;
        letter-spacing: 0.6px;
        color: {{ $accent }};
        margin: 0 0 6px 0;
    }

    .bill-to-box {
        background-color: {{ $accentTint }};
        border: 1px solid #D6E3F3;
        border-radius: 4px;
        padding: 12px 16px;
        margin-bottom: 4px;
    }

    .bill-to-name {
        font-size: 12pt;
        font-weight: bold;
        color: {{ $ink }};
        margin: 0 0 3px 0;
    }

    .bill-to-meta {
        font-size: 9pt;
        color: {{ $ink }};
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
        letter-spacing: 0.5px;
        color: {{ $ink }};
        text-align: left;
        padding: 8px 8px;
        background-color: {{ $accentTint }};
        border-top: 1.5px solid {{ $accent }};
        border-bottom: 1.5px solid {{ $accent }};
    }

    table.items thead th:first-child {
        border-left: 1.5px solid {{ $accent }};
    }

    table.items thead th:last-child {
        border-right: 1.5px solid {{ $accent }};
    }

    table.items thead th.num {
        text-align: right;
    }

    table.items tbody td {
        padding: 8px 8px;
        border-bottom: 1px solid #E5E7EB;
        font-size: 9.5pt;
        color: {{ $ink }};
        vertical-align: top;
    }

    table.items tbody td.num {
        text-align: right;
        white-space: nowrap;
    }

    table.items tbody tr:nth-child(even) {
        background-color: #F7FAFD;
    }

    .totals {
        width: 300px;
        margin-left: auto;
        margin-top: 16px;
        border: 1px solid #D6E3F3;
        border-radius: 4px;
        overflow: hidden;
    }

    .totals table {
        width: 100%;
        border-collapse: collapse;
    }

    .totals td {
        padding: 7px 14px;
        font-size: 9.5pt;
        color: {{ $ink }};
        white-space: nowrap;
    }

    .totals td.label {
        text-align: left;
    }

    .totals td.value {
        text-align: right;
    }

    .totals tr.total-row td {
        background-color: {{ $accent }};
        padding-top: 10px;
        padding-bottom: 10px;
        font-size: 13pt;
        font-weight: bold;
        color: #FFFFFF;
    }

    .footer {
        position: fixed;
        bottom: -85px;
        left: -48px;
        right: -48px;
        height: 85px;
    }

    .footer-band {
        height: 3px;
        background-color: {{ $accent }};
    }

    .footer-notes {
        font-size: 8.5pt;
        color: {{ $ink }};
        line-height: 1.6;
    }

    .status-badge {
        display: inline-block;
        font-size: 7.5pt;
        font-weight: bold;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: #FFFFFF;
        background-color: {{ $accentDark }};
        padding: 3px 10px;
        border-radius: 3px;
    }
</style>
