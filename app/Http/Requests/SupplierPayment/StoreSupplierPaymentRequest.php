<?php

namespace App\Http\Requests\SupplierPayment;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSupplierPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $companyId = $this->user()->company_id;

        return [
            'supplier_id' => ['required', Rule::exists('suppliers', 'id')->where('company_id', $companyId)],
            'supplier_bill_id' => ['required', Rule::exists('supplier_bills', 'id')->where('company_id', $companyId)],
            'payment_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'payment_type_id' => ['nullable', 'integer'],
            'reference_number' => ['nullable', 'string', 'max:255'],
        ];
    }
}
