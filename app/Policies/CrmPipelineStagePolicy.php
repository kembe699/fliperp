<?php

namespace App\Policies;

use App\Models\CrmPipelineStage;
use App\Models\User;

class CrmPipelineStagePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('crm-pipeline-stages.view');
    }

    public function view(User $user, CrmPipelineStage $stage): bool
    {
        return $user->can('crm-pipeline-stages.view') && $stage->company_id === $user->company_id;
    }

    public function create(User $user): bool
    {
        return $user->can('crm-pipeline-stages.create');
    }

    public function update(User $user, CrmPipelineStage $stage): bool
    {
        return $user->can('crm-pipeline-stages.update') && $stage->company_id === $user->company_id;
    }

    public function delete(User $user, CrmPipelineStage $stage): bool
    {
        return $user->can('crm-pipeline-stages.delete') && $stage->company_id === $user->company_id;
    }

    public function reorder(User $user): bool
    {
        return $user->can('crm-pipeline-stages.reorder');
    }
}
