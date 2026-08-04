<?php

namespace App\Http\Requests\StockTransfer;

use Illuminate\Foundation\Http\FormRequest;

class CompleteStockTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'allow_negative_stock' => ['nullable', 'boolean'],
        ];
    }
}
