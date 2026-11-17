<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PaymentRequestResource;
use App\Models\PaymentRequest;
use App\Models\PaymentRequestAttachment;
use App\Services\Finance\PaymentRequestService;
use App\Services\Grants\DimensionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Accounting › Payment Requests: raise, approve in steps by amount, pay. */
class PaymentRequestController extends Controller
{
    public function __construct(protected PaymentRequestService $requests) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', PaymentRequest::class);

        $filters = $request->validate([
            'status' => ['nullable', Rule::in(PaymentRequest::STATUSES)],
            'requested_by' => ['nullable', 'integer'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'project_id' => ['nullable', 'integer'],
            'grant_id' => ['nullable', 'integer'],
            'tab' => ['nullable', Rule::in(['all', 'mine', 'awaiting'])],
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        return $this->paginated(PaymentRequestResource::collection(
            $this->requests->paginate($request->user(), $filters, min($request->integer('per_page', 15), 500))
        ));
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', PaymentRequest::class);
        $created = $this->requests->create($this->validated($request, true), $request->user());

        return $this->success(new PaymentRequestResource($created), "Payment request {$created->reference_number} created.", 201);
    }

    public function show(PaymentRequest $paymentRequest): JsonResponse
    {
        $this->authorize('view', $paymentRequest);

        return $this->success(new PaymentRequestResource($paymentRequest->load(PaymentRequestService::LOADED)));
    }

    public function update(Request $request, PaymentRequest $paymentRequest): JsonResponse
    {
        $this->authorize('update', $paymentRequest);

        return $this->success(new PaymentRequestResource($this->requests->update($paymentRequest, $this->validated($request, false), $request->user())), 'Payment request updated.');
    }

    public function destroy(PaymentRequest $paymentRequest): JsonResponse
    {
        $this->authorize('delete', $paymentRequest);
        $this->requests->delete($paymentRequest);

        return $this->success(null, 'Payment request deleted.');
    }

    public function submit(Request $request, PaymentRequest $paymentRequest): JsonResponse
    {
        $this->authorize('submit', $paymentRequest);
        $comment = $request->validate(['comment' => ['nullable', 'string', 'max:2000']])['comment'] ?? null;

        return $this->success(new PaymentRequestResource($this->requests->submit($paymentRequest, $request->user(), $comment)), 'Payment request submitted for approval.');
    }

    public function approve(Request $request, PaymentRequest $paymentRequest): JsonResponse
    {
        $this->authorize('approve', $paymentRequest);
        $comment = $request->validate(['comment' => ['nullable', 'string', 'max:2000']])['comment'] ?? null;
        $result = $this->requests->approve($paymentRequest, $request->user(), $comment);

        return $this->success(new PaymentRequestResource($result), $result->status === 'approved' ? 'Payment request fully approved.' : 'Step approved. Sent to the next approver.');
    }

    public function reject(Request $request, PaymentRequest $paymentRequest): JsonResponse
    {
        $this->authorize('approve', $paymentRequest);
        $comment = $request->validate(['comment' => ['required', 'string', 'max:2000']], ['comment.required' => 'Give the requester a reason for rejecting.'])['comment'];

        return $this->success(new PaymentRequestResource($this->requests->reject($paymentRequest, $request->user(), $comment)), 'Payment request rejected and returned to the requester.');
    }

    public function pay(Request $request, PaymentRequest $paymentRequest): JsonResponse
    {
        $this->authorize('pay', $paymentRequest);
        $data = $request->validate([
            'payment_date' => ['required', 'date'],
            'payment_type_id' => ['nullable', Rule::exists('payment_types', 'id')->where('company_id', $request->user()->company_id)],
            'reference' => ['nullable', 'string', 'max:255'],
            'exchange_rate' => ['nullable', 'numeric', 'gt:0'],
        ]);

        return $this->success(new PaymentRequestResource($this->requests->pay($paymentRequest, $request->user(), $data)), 'Payment recorded.');
    }

    public function cancel(Request $request, PaymentRequest $paymentRequest): JsonResponse
    {
        $this->authorize('cancel', $paymentRequest);
        $comment = $request->validate(['comment' => ['nullable', 'string', 'max:2000']])['comment'] ?? null;

        return $this->success(new PaymentRequestResource($this->requests->cancel($paymentRequest, $request->user(), $comment)), 'Payment request cancelled.');
    }

    public function storeAttachments(Request $request, PaymentRequest $paymentRequest): JsonResponse
    {
        $this->authorize('update', $paymentRequest);
        $request->validate([
            'attachments' => ['required', 'array', 'max:10'],
            'attachments.*' => ['file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,webp,doc,docx,xls,xlsx'],
        ]);

        return $this->success(new PaymentRequestResource($this->requests->addAttachments($paymentRequest, $request->file('attachments'), $request->user())), 'Document added.');
    }

    public function downloadAttachment(PaymentRequest $paymentRequest, PaymentRequestAttachment $attachment): StreamedResponse
    {
        $this->authorize('view', $paymentRequest);
        abort_unless($attachment->payment_request_id === $paymentRequest->id, 404);

        return Storage::disk(PaymentRequestService::ATTACHMENT_DISK)->download($attachment->file_path, $attachment->file_name);
    }

    public function destroyAttachment(Request $request, PaymentRequest $paymentRequest, PaymentRequestAttachment $attachment): JsonResponse
    {
        $this->authorize('update', $paymentRequest);

        return $this->success(new PaymentRequestResource($this->requests->deleteAttachment($paymentRequest, $attachment, $request->user())), 'Document removed.');
    }

    public function pdf(Request $request, PaymentRequest $paymentRequest): Response
    {
        $this->authorize('view', $paymentRequest);

        return $this->pdfResponse($this->requests->pdf($paymentRequest), "payment-request-{$paymentRequest->reference_number}.pdf", $request->boolean('download'));
    }

    protected function validated(Request $request, bool $creating): array
    {
        $companyId = $request->user()->company_id;
        $req = $creating ? 'required' : 'sometimes';

        return $request->validate([
            'request_date' => [$req, 'date'],
            'supplier_id' => ['nullable', Rule::exists('suppliers', 'id')->where('company_id', $companyId)],
            'payee_name' => ['nullable', 'string', 'max:255'],
            'description' => [$req, 'string', 'max:2000'],
            'amount' => [$req, 'numeric', 'gt:0', 'max:9999999999999'],
            'currency_code' => ['nullable', 'string', 'size:3', 'exists:currencies,code'],
            'exchange_rate' => ['nullable', 'numeric', 'gt:0'],
            'expense_account_id' => ['nullable', Rule::exists('chart_of_accounts', 'id')->where('company_id', $companyId)->whereIn('type', ['expense', 'asset'])],
            'purchase_order_id' => ['nullable', 'integer'],
            'rfq_id' => ['nullable', 'integer'],
            'supplier_bill_id' => ['nullable', 'integer'],
            ...DimensionService::lineRules(),
        ]);
    }
}
