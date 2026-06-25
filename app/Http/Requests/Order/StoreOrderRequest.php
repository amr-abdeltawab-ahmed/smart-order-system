<?php

namespace App\Http\Requests\Order;

use Illuminate\Foundation\Http\FormRequest;

class StoreOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'items'                  => ['required', 'array', 'min:1', 'max:50'],
            'items.*.product_name'   => ['required', 'string', 'max:255'],
            'items.*.quantity'       => ['required', 'integer', 'min:1', 'max:999'],
            'items.*.price'          => ['required', 'numeric', 'min:0.01', 'max:999999.99'],
        ];
    }
}
