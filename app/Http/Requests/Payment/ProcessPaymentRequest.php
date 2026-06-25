<?php

namespace App\Http\Requests\Payment;

use App\Payments\PaymentGatewayManager;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProcessPaymentRequest extends FormRequest
{
    public function __construct(private readonly PaymentGatewayManager $gatewayManager)
    {
        parent::__construct();
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'order_id'       => ['required', 'integer', 'exists:orders,id'],
            'payment_method' => ['required', 'string', Rule::in($this->gatewayManager->supported())],
        ];
    }

    public function messages(): array
    {
        $supported = implode(', ', $this->gatewayManager->supported());

        return [
            'payment_method.in' => "The payment method must be one of: {$supported}.",
        ];
    }
}
