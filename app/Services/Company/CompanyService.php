<?php

namespace App\Services\Company;

use App\Models\Company;
use App\Services\Media\ImageUploadService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;

class CompanyService
{
    public function __construct(protected ImageUploadService $imageUploadService) {}

    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return Company::query()
            ->when($filters['company_id'] ?? null, fn ($query, $id) => $query->where('id', $id))
            ->latest()
            ->paginate($perPage);
    }

    public function create(array $data): Company
    {
        return Company::create($data);
    }

    public function update(Company $company, array $data): Company
    {
        $company->update($data);

        return $company;
    }

    public function delete(Company $company): void
    {
        $company->delete();
    }

    public function uploadLogo(Company $company, UploadedFile $file): Company
    {
        $url = $this->imageUploadService->replace($file, 'company', $company->logo_url);
        $company->update(['logo_url' => $url]);

        return $company;
    }

    public function deleteLogo(Company $company): Company
    {
        $this->imageUploadService->delete($company->logo_url);
        $company->update(['logo_url' => null]);

        return $company;
    }
}
