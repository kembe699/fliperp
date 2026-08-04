<?php

namespace App\Http\Requests\CustomerPayment;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCustomerPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $companyId = $this->user()->company_id;

        return [
            'customer_id' => ['required', Rule::exists('customers', 'id')->where('company_id', $companyId)],
            'invoice_id' => ['required', Rule::exists('invoices', 'id')->where('company_id', $companyId)],
            'payment_type_id' => ['required', Rule::exists('payment_types', 'id')->where('company_id', $companyId)],
            'payment_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'reference_number' => ['nullable', 'string', 'max:255'],
        ];
    }
}
