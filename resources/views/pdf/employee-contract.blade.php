<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Employment Contract - {{ $employee->first_name }} {{ $employee->last_name }}</title>
    @include('pdf.partials.styles')
    <style>
        .contract-body {
            font-size: 10pt;
            line-height: 1.6;
            color: #1A1A1A;
        }

        .contract-body p {
            margin: 0 0 12px 0;
        }

        .signature-block {
            margin-top: 48px;
        }

        .signature-row {
            width: 100%;
            border-collapse: collapse;
        }

        .signature-row td {
            width: 50%;
            vertical-align: bottom;
            padding-right: 24px;
        }

        .signature-image {
            display: block;
            max-width: 200px;
            max-height: 70px;
            width: auto;
            height: auto;
            margin-bottom: 4px;
        }

        .signature-line {
            border-top: 1px solid #1A1A1A;
            padding-top: 4px;
            font-size: 8.5pt;
            color: #6B7280;
        }

        .signature-placeholder {
            height: 70px;
        }

        .signature-name {
            font-size: 10pt;
            font-weight: bold;
            color: #1A1A1A;
            margin: 0 0 2px 0;
        }
    </style>
</head>
<body>
    @include('pdf.partials.document-header', [
        'companyName' => $company->name ?? 'Company',
        'branchName' => $employee->branch?->name,
        'branchAddress' => $employee->branch?->address,
        'branchPhone' => $employee->branch?->phone,
        'documentType' => 'Employment Contract',
        'referenceNumber' => 'CON-'.str_pad((string) $contract->id, 5, '0', STR_PAD_LEFT),
        'documentDate' => \Illuminate\Support\Carbon::parse($contract->start_date)->format('d M Y'),
        'metaRows' => [
            'Employee' => trim($employee->first_name.' '.$employee->last_name),
            'Status' => strtoupper($contract->status),
        ],
    ])

    @include('pdf.partials.footer-script', ['companyName' => $company->name ?? 'Company'])

    <div class="contract-body">
        {!! $contract->contract_body !!}
    </div>

    <div class="signature-block">
        <table class="signature-row">
            <tr>
                <td>
                    @if ($contract->status === 'signed' && $contract->signature_data)
                        <img src="{{ $contract->signature_data }}" alt="Signature" class="signature-image">
                    @else
                        <div class="signature-placeholder"></div>
                    @endif
                    <div class="signature-line">
                        <p class="signature-name">{{ $contract->signed_by_name ?? trim($employee->first_name.' '.$employee->last_name) }}</p>
                        Employee Signature
                        @if ($contract->signed_at)
                            &middot; Signed {{ $contract->signed_at->format('d M Y, H:i') }}
                        @endif
                    </div>
                </td>
                <td>
                    <div class="signature-placeholder"></div>
                    <div class="signature-line">
                        For {{ $company->name ?? 'Company' }}
                    </div>
                </td>
            </tr>
        </table>
    </div>
</body>
</html>
