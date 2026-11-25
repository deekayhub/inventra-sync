<?php

namespace App\Http\Requests\Orders;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOrderFulfilmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        $order = $this->route('order');

        return [
            'items' => ['sometimes', 'array', 'max:200'],
            'items.*.order_item_id' => [
                'required',
                'integer',
                Rule::exists('order_items', 'id')->where(fn ($query): mixed => $query->where('order_id', $order?->id)),
            ],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'carrier' => ['nullable', 'string', 'max:255'],
            'tracking_number' => ['nullable', 'string', 'max:255'],
        ];
    }
}
