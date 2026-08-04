<x-mail::message>
Hello {{ $recipientName }},

Please find attached {{ $documentType }} **{{ $referenceNumber }}** from {{ $companyName }}.

@if ($documentType === 'Purchase Order')
Please review the attached order and confirm receipt.
@elseif ($documentType === 'Invoice')
Please arrange payment by the due date shown on the attached invoice.
@else
Please review the attached document at your convenience.
@endif

Thanks,<br>
{{ $companyName }}
</x-mail::message>
