<?php

namespace App\Http\Requests\Order;

use App\Enums\OrderStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status'                 => ['sometimes', 'string', Rule::in(OrderStatus::values())],
            'items'                  => ['sometimes', 'array', 'min:1', 'max:50'],
            'items.*.product_name'   => ['required_with:items', 'string', 'max:255'],
            'items.*.quantity'       => ['required_with:items', 'integer', 'min:1', 'max:999'],
            'items.*.price'          => ['required_with:items', 'numeric', 'min:0.01', 'max:999999.99'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if (! $this->filled('status') && ! $this->filled('items')) {
                $validator->errors()->add('body', 'At least one of status or items must be provided.');
            }
        });
    }
}
