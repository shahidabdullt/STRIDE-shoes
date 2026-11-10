<?php

namespace App\Http\Requests\Shop;

use Illuminate\Foundation\Http\FormRequest;

class CheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'customer_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email:rfc', 'max:255'],
            'address_line1' => ['required', 'string', 'max:150'],
            'address_line2' => ['nullable', 'string', 'max:150'],
            'city' => ['required', 'string', 'max:80'],
            'state' => ['required', 'regex:/^[A-Za-z]{2}$/'],
            'postal_code' => ['required', 'regex:/^\d{5}(-\d{4})?$/'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'state.regex' => 'Enter the two-letter US state code, for example CA.',
            'postal_code.regex' => 'Enter a US ZIP code such as 94105.',
        ];
    }

    /**
     * Only the validated customer fields, shaped for PlaceOrder. Nothing price-related is read from the request.
     *
     * @return array{customer_name: string, email: string, address: array<string, string>}
     */
    public function customer(): array
    {
        $data = $this->validated();

        return [
            'customer_name' => $data['customer_name'],
            'email' => $data['email'],
            'address' => [
                'line1' => $data['address_line1'],
                'line2' => $data['address_line2'] ?? '',
                'city' => $data['city'],
                'state' => strtoupper($data['state']),
                'postal_code' => $data['postal_code'],
                'country' => 'US',
            ],
        ];
    }
}
