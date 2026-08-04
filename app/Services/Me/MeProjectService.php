<?php

namespace App\Services\Me;

use App\Models\MeProject;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class MeProjectService
{
    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return MeProject::query()->latest()->paginate($perPage);
    }

    public function create(array $data): MeProject
    {
        return MeProject::create($data);
    }

    public function update(MeProject $project, array $data): MeProject
    {
        $project->update($data);

        return $project;
    }

    public function delete(MeProject $project): void
    {
        $project->delete();
    }
}
