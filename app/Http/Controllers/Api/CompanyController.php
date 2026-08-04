<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Company\StoreCompanyLogoRequest;
use App\Http\Requests\Company\StoreCompanyRequest;
use App\Http\Requests\Company\UpdateCompanyRequest;
use App\Http\Resources\CompanyResource;
use App\Models\Company;
use App\Services\Company\CompanyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CompanyController extends Controller
{
    public function __construct(protected CompanyService $companyService) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Company::class);

        $companies = $this->companyService->paginate(
            ['company_id' => $request->user()->company_id],
            $request->integer('per_page', 15),
        );

        return $this->paginated(CompanyResource::collection($companies));
    }

    public function store(StoreCompanyRequest $request): JsonResponse
    {
        $this->authorize('create', Company::class);

        $company = $this->companyService->create($request->validated());

        return $this->success(new CompanyResource($company), 'Company created successfully.', 201);
    }

    public function show(Company $company): JsonResponse
    {
        $this->authorize('view', $company);

        return $this->success(new CompanyResource($company));
    }

    public function update(UpdateCompanyRequest $request, Company $company): JsonResponse
    {
        $this->authorize('update', $company);

        $company = $this->companyService->update($company, $request->validated());

        return $this->success(new CompanyResource($company), 'Company updated successfully.');
    }

    public function destroy(Company $company): JsonResponse
    {
        $this->authorize('delete', $company);

        $this->companyService->delete($company);

        return $this->success(null, 'Company deleted successfully.');
    }

    public function uploadLogo(StoreCompanyLogoRequest $request): JsonResponse
    {
        $company = $this->currentCompany($request);
        $this->authorize('update', $company);

        $company = $this->companyService->uploadLogo($company, $request->file('image'));

        return $this->success(new CompanyResource($company), 'Company logo uploaded successfully.');
    }

    public function deleteLogo(Request $request): JsonResponse
    {
        $company = $this->currentCompany($request);
        $this->authorize('update', $company);

        $company = $this->companyService->deleteLogo($company);

        return $this->success(new CompanyResource($company), 'Company logo removed successfully.');
    }

    protected function currentCompany(Request $request): Company
    {
        $companyId = $request->user()->company_id;

        if (! $companyId) {
            throw ValidationException::withMessages([
                'company' => ['Your account is not associated with a single company. Use the companies endpoint directly.'],
            ]);
        }

        return Company::findOrFail($companyId);
    }
}
