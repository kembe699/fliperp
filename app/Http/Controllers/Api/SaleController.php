<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sale\AddSalePaymentRequest;
use App\Http\Requests\Sale\StoreSaleRequest;
use App\Http\Requests\Sale\UpdateSaleRequest;
use App\Http\Resources\SalePaymentResource;
use App\Http\Resources\SaleResource;
use App\Models\Company;
use App\Models\Sale;
use App\Services\Pos\SaleService;
use App\Support\Url;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use chillerlan\QRCode\Output\QRGdImagePNG;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Picqer\Barcode\BarcodeGeneratorPNG;

class SaleController extends Controller
{
    public function __construct(protected SaleService $saleService) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Sale::class);

        $sales = $this->saleService->paginate(
            $request->only('status', 'branch_id', 'served_by', 'payment_type_id', 'search', 'from', 'to', 'cash_drawer_session_id'),
            $request->integer('per_page', 15),
        );

        return $this->paginated(SaleResource::collection($sales));
    }

    public function store(StoreSaleRequest $request): JsonResponse
    {
        $this->authorize('create', Sale::class);

        $sale = $this->saleService->create($request->validated());

        return $this->success(new SaleResource($sale), 'Sale created successfully.', 201);
    }

    public function show(Sale $sale): JsonResponse
    {
        $this->authorize('view', $sale);

        return $this->success(new SaleResource($sale->load('items', 'payments')));
    }

    public function update(UpdateSaleRequest $request, Sale $sale): JsonResponse
    {
        $this->authorize('update', $sale);

        $sale = $this->saleService->update($sale, $request->validated());

        return $this->success(new SaleResource($sale), 'Sale updated successfully.');
    }

    public function destroy(Sale $sale): JsonResponse
    {
        $this->authorize('delete', $sale);

        $this->saleService->delete($sale);

        return $this->success(null, 'Sale deleted successfully.');
    }

    public function hold(Sale $sale): JsonResponse
    {
        $this->authorize('hold', $sale);

        $sale = $this->saleService->hold($sale);

        return $this->success(new SaleResource($sale), 'Sale held successfully.');
    }

    public function complete(Sale $sale): JsonResponse
    {
        $this->authorize('complete', $sale);

        $sale = $this->saleService->complete($sale);

        return $this->success(new SaleResource($sale), 'Sale completed successfully.');
    }

    public function void(Sale $sale): JsonResponse
    {
        $this->authorize('void', $sale);

        $sale = $this->saleService->void($sale);

        return $this->success(new SaleResource($sale), 'Sale voided successfully.');
    }

    public function refund(Sale $sale): JsonResponse
    {
        $this->authorize('refund', $sale);

        $sale = $this->saleService->refund($sale);

        return $this->success(new SaleResource($sale), 'Sale refunded successfully.');
    }

    public function addPayment(AddSalePaymentRequest $request, Sale $sale): JsonResponse
    {
        $this->authorize('addPayment', $sale);

        $payment = $this->saleService->addPayment($sale, $request->validated());

        return $this->success(new SalePaymentResource($payment), 'Payment recorded successfully.', 201);
    }

    public function held(): JsonResponse
    {
        $this->authorize('viewAny', Sale::class);

        $sales = $this->saleService->heldForCurrentSession();

        return $this->success(SaleResource::collection($sales));
    }

    public function receipt(Request $request, Sale $sale): Response
    {
        $this->authorize('view', $sale);

        $sale->load(['items.product', 'items.variant', 'payments.paymentType', 'servedBy', 'branch', 'customer']);
        $company = Company::find($sale->company_id);

        $verifyUrl = Url::withScheme(config('app.frontend_url'))."/verify/{$sale->id}";

        // dompdf in this stack doesn't rasterize inline <svg> markup at all
        // (confirmed directly — even a bare hand-written <svg><rect/></svg>
        // renders as nothing), so both codes are generated as raster PNGs
        // and embedded as ordinary base64 data-URI <img> tags instead, the
        // same way the company logo already is. Both libraries render via
        // GD rather than Imagick, which isn't installed here.
        $barcodePng = base64_encode(
            (new BarcodeGeneratorPNG)->getBarcode($sale->reference_number, BarcodeGeneratorPNG::TYPE_CODE_128, 2, 40),
        );
        // render() already returns a ready-to-use "data:image/png;base64,..."
        // URI here (the imageBase64 option controls something else — the raw
        // bytes are never returned plain), unlike BarcodeGeneratorPNG above.
        $qrDataUri = (new QRCode(new QROptions([
            'outputInterface' => QRGdImagePNG::class,
            'scale' => 4,
            'margin' => 1,
        ])))->render($verifyUrl);

        // 80mm thermal paper, width fixed at 226.77pt (80mm); height is
        // estimated from content so the roll isn't cut mid-receipt or left
        // with a long blank tail.
        $pdf = Pdf::loadView('pdf.receipt', [
            'sale' => $sale,
            'company' => $company,
            'branch' => $sale->branch,
            'currencyCode' => $company?->currency_code ?? 'USD',
            'barcodePng' => $barcodePng,
            'qrDataUri' => $qrDataUri,
            'verificationCode' => now()->format('YmdHis').random_int(100, 999),
        ])->setPaper([0, 0, 226.77, $this->receiptHeightPoints($sale, $company)]);

        return $this->pdfResponse($pdf, "receipt-{$sale->reference_number}.pdf", $request->boolean('download'));
    }

    protected function receiptHeightPoints(Sale $sale, ?Company $company): float
    {
        $base = $company?->logoFilePath() ? 462.0 : 432.0;
        $perItem = 26.0;
        $perPayment = 14.0;

        $height = $base + ($sale->items->count() * $perItem) + ($sale->payments->count() * $perPayment);

        return max($height, 240.0);
    }
}
