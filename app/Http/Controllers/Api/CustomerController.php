<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\StoreCustomerRequest;
use App\Http\Requests\Customer\UpdateCustomerRequest;
use App\Http\Resources\CustomerResource;
use App\Models\Company;
use App\Models\Customer;
use App\Services\Pos\CustomerService;
use App\Services\Pos\CustomerStatementService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class CustomerController extends Controller
{
    public function __construct(
        protected CustomerService $customerService,
        protected CustomerStatementService $customerStatementService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Customer::class);

        $customers = $this->customerService->paginate(
            [
                ...$request->only('customer_type', 'branch_id', 'search'),
                'is_active' => $request->has('is_active') ? $request->boolean('is_active') : null,
            ],
            $request->integer('per_page', 15),
        );

        return $this->paginated(CustomerResource::collection($customers));
    }

    public function store(StoreCustomerRequest $request): JsonResponse
    {
        $this->authorize('create', Customer::class);

        $customer = $this->customerService->create($request->validated());

        return $this->success(new CustomerResource($customer), 'Customer created successfully.', 201);
    }

    public function show(Customer $customer): JsonResponse
    {
        $this->authorize('view', $customer);

        return $this->success(new CustomerResource($customer));
    }

    public function update(UpdateCustomerRequest $request, Customer $customer): JsonResponse
    {
        $this->authorize('update', $customer);

        $customer = $this->customerService->update($customer, $request->validated());

        return $this->success(new CustomerResource($customer), 'Customer updated successfully.');
    }

    public function destroy(Customer $customer): JsonResponse
    {
        $this->authorize('delete', $customer);

        $this->customerService->delete($customer);

        return $this->success(null, 'Customer deleted successfully.');
    }

    public function statement(Customer $customer): JsonResponse
    {
        $this->authorize('view', $customer);

        return $this->success($this->customerStatementService->statement($customer));
    }

    public function statementPdf(Request $request, Customer $customer): Response
    {
        $this->authorize('view', $customer);

        $statement = $this->customerStatementService->statement($customer);
        $company = Company::find($customer->company_id);

        $pdf = Pdf::loadView('pdf.customer-statement', [
            'statement' => $statement,
            'company' => $company,
            'customer' => $customer,
            'currencyCode' => $company?->currency_code ?? 'USD',
        ])->setPaper('a4', 'portrait');

        $filename = 'statement-'.str($customer->name)->slug().'.pdf';

        return $this->pdfResponse($pdf, $filename, $request->boolean('download'));
    }
}
