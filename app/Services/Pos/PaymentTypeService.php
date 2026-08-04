<?php

namespace App\Services\Pos;

use App\Models\PaymentType;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;

class PaymentTypeService
{
    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return PaymentType::query()->latest()->paginate($perPage);
    }

    public function create(array $data): PaymentType
    {
        return PaymentType::create($data);
    }

    public function update(PaymentType $paymentType, array $data): PaymentType
    {
        $paymentType->update($data);

        return $paymentType;
    }

    public function delete(PaymentType $paymentType): void
    {
        if ($paymentType->salePayments()->exists() || $paymentType->supplierPayments()->exists()) {
            throw ValidationException::withMessages([
                'payment_type' => ['Cannot delete a payment type that already has payments recorded against it. Deactivate it instead.'],
            ]);
        }

        $paymentType->delete();
    }
}
