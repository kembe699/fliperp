{{-- Expects: $companyName. Renders a thin rule + quiet "Company · Page X of Y" via dompdf's page_text API, and a small "Powered by FlipERP" mark. --}}
@php($fliperpLogo = \App\Support\Brand::logoDataUri())
<div class="footer">
    <hr class="rule" style="margin-top: 0;">
    @if ($fliperpLogo)
        <div style="text-align: right; margin-top: 2px; font-size: 7pt; color: #9CA3AF;">
            Powered by <img src="{{ $fliperpLogo }}" alt="FlipERP" style="height: 13px; width: auto; vertical-align: middle;">
        </div>
    @endif
</div>
<script type="text/php">
if (isset($pdf)) {
    $font = $fontMetrics->getFont("Helvetica", "normal");
    $size = 8;
    $color = array(0.42, 0.45, 0.5);
    $companyName = {!! var_export($companyName, true) !!};
    $text = $companyName . "  ·  Page {PAGE_NUM} of {PAGE_COUNT}";
    $width = $fontMetrics->getTextWidth($text, $font, $size);
    $x = ($pdf->get_width() - $width) / 2;
    $y = $pdf->get_height() - 46;
    $pdf->page_text($x, $y, $text, $font, $size, $color);
}
</script>
