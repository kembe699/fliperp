<?php

namespace App\Policies;

use App\Models\Invoice;
use App\Models\User;

class InvoicePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('invoices.view');
    }

    public function view(User $user, Invoice $invoice): bool
    {
        return $user->can('invoices.view') && $invoice->company_id === $user->company_id;
    }

    public function create(User $user): bool
    {
        return $user->can('invoices.create');
    }

    public function update(User $user, Invoice $invoice): bool
    {
        return $user->can('invoices.update') && $invoice->company_id === $user->company_id;
    }

    public function delete(User $user, Invoice $invoice): bool
    {
        return $user->can('invoices.delete') && $invoice->company_id === $user->company_id;
    }

    public function send(User $user, Invoice $invoice): bool
    {
        return $user->can('invoices.send') && $invoice->company_id === $user->company_id;
    }

    public function cancel(User $user, Invoice $invoice): bool
    {
        return $user->can('invoices.cancel') && $invoice->company_id === $user->company_id;
    }
}
