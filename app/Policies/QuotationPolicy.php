<?php

namespace App\Policies;

use App\Models\Quotation;
use App\Models\User;

class QuotationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('quotations.view');
    }

    public function view(User $user, Quotation $quotation): bool
    {
        return $user->can('quotations.view') && $quotation->company_id === $user->company_id;
    }

    public function create(User $user): bool
    {
        return $user->can('quotations.create');
    }

    public function update(User $user, Quotation $quotation): bool
    {
        return $user->can('quotations.update') && $quotation->company_id === $user->company_id;
    }

    public function delete(User $user, Quotation $quotation): bool
    {
        return $user->can('quotations.delete') && $quotation->company_id === $user->company_id;
    }

    public function send(User $user, Quotation $quotation): bool
    {
        return $user->can('quotations.send') && $quotation->company_id === $user->company_id;
    }

    public function accept(User $user, Quotation $quotation): bool
    {
        return $user->can('quotations.accept') && $quotation->company_id === $user->company_id;
    }

    public function reject(User $user, Quotation $quotation): bool
    {
        return $user->can('quotations.reject') && $quotation->company_id === $user->company_id;
    }

    public function convertToInvoice(User $user, Quotation $quotation): bool
    {
        return $user->can('quotations.convert-to-invoice') && $quotation->company_id === $user->company_id;
    }
}
