<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'merchant_id' => ['required', 'integer', 'exists:merchants,id'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'currency' => ['required', 'string', 'size:3'],
            'billing_interval' => ['required', 'in:monthly,yearly'],
            'base_price' => ['required', 'decimal:0,2', 'min:0'],
            'included_units' => ['required', 'integer', 'min:0'],
            'overage_unit_price' => ['nullable', 'decimal:0,2', 'min:0'],
            'active' => ['sometimes', 'boolean'],
        ];
    }
}
