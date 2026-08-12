{{-- Expects: $companyName, $branchName, $branchAddress, $branchPhone, $documentType, $referenceNumber, $documentDate, $metaRows (array<string,string>), optional $referenceLabel (default "Reference"), optional $company (for logo) --}}
@php($referenceLabel = $referenceLabel ?? 'Reference')
@php($logoPath = ($company ?? null)?->logoDataUri())
<div class="header">
    <div class="header-inner">
        <table style="width: 100%; border-collapse: collapse;">
            <tr>
                <td style="width: 55%; vertical-align: top;">
                    @if ($logoPath)
                        <img src="{{ $logoPath }}" alt="{{ $companyName }}" class="company-logo">
                    @else
                        <p class="company-name">{{ $companyName }}</p>
                    @endif
                    <div class="company-meta">
                        @if (!empty($branchName))
                            {{ $branchName }}<br>
                        @endif
                        @if (!empty($branchAddress))
                            {{ $branchAddress }}<br>
                        @endif
                        @if (!empty($branchPhone))
                            {{ $branchPhone }}
                        @endif
                    </div>
                </td>
                <td style="width: 45%; vertical-align: top;">
                    <p class="doc-title">{{ $documentType }}</p>
                    <div class="doc-meta-row">
                        <span class="doc-meta-label">{{ $referenceLabel }}</span> {{ $referenceNumber }}<br>
                        <span class="doc-meta-label">Date</span> {{ $documentDate }}
                        @foreach ($metaRows ?? [] as $label => $value)
                            <br><span class="doc-meta-label">{{ $label }}</span> {{ $value }}
                        @endforeach
                    </div>
                </td>
            </tr>
        </table>
    </div>
    <hr class="rule">
</div>
