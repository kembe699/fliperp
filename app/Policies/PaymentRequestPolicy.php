<?php

namespace App\Policies;

use App\Models\PaymentRequest;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Who may do what with a payment request. Status rules (what can happen from
 * which status) and approval-step rules live in PaymentRequestService and are
 * enforced there too, so neither layer relies on the other or on the screen.
 */
class PaymentRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('payment-requests.view');
    }

    public function view(User $user, PaymentRequest $request): bool
    {
        return $this->own($user, $request) && ($user->can('payment-requests.view') || $request->requested_by === $user->id);
    }

    public function create(User $user): bool
    {
        return $user->can('payment-requests.create');
    }

    public function update(User $user, PaymentRequest $request): bool
    {
        return $this->own($user, $request) && $user->can('payment-requests.update');
    }

    public function delete(User $user, PaymentRequest $request): bool
    {
        return $this->own($user, $request) && $user->can('payment-requests.delete');
    }

    public function submit(User $user, PaymentRequest $request): bool
    {
        return $this->own($user, $request) && $user->can('payment-requests.submit');
    }

    public function approve(User $user, PaymentRequest $request): Response
    {
        if (! $this->own($user, $request) || ! $user->can('payment-requests.approve')) {
            return Response::deny('You do not have permission to approve payment requests.');
        }
        // Never self-approval, whatever the company's "allow self-approval" setting says.
        if ($request->requested_by === $user->id) {
            return Response::deny('You raised this payment request, so someone else must approve it.');
        }

        return Response::allow();
    }

    public function pay(User $user, PaymentRequest $request): bool
    {
        return $this->own($user, $request) && $user->can('payment-requests.pay');
    }

    public function cancel(User $user, PaymentRequest $request): bool
    {
        return $this->own($user, $request)
            && ($user->can('payment-requests.cancel') || ($request->requested_by === $user->id && $request->status === 'draft'));
    }

    protected function own(User $user, PaymentRequest $request): bool
    {
        return $request->company_id === $user->company_id;
    }
}
