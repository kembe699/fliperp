{{-- Expects: $companyName. Renders a repeating footer band + "Company — Page X of Y" via dompdf's page_text API. --}}
<div class="footer">
    <div class="footer-band"></div>
</div>
<script type="text/php">
if (isset($pdf)) {
    $font = $fontMetrics->getFont("Helvetica", "bold");
    $size = 8;
    $color = array(0, 0, 0);
    $companyName = {!! var_export($companyName, true) !!};
    $text = strtoupper($companyName) . "   |   PAGE {PAGE_NUM} OF {PAGE_COUNT}";
    $width = $fontMetrics->getTextWidth($text, $font, $size);
    $x = ($pdf->get_width() - $width) / 2;
    $y = $pdf->get_height() - 60;
    $pdf->page_text($x, $y, $text, $font, $size, $color);
}
</script>
