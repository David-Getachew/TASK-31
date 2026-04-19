<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class InitiatePaymentRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'method'   => ['required', 'string', Rule::in(array_map(fn (PaymentMethod $m) => $m->value, PaymentMethod::cases()))],
            'kiosk_id' => ['nullable', 'string', 'max:100'],
        ];
    }
}
