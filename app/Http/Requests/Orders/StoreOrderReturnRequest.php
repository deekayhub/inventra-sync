<?php

namespace App\Http\Requests\Orders;

use App\Models\OrderReturn;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOrderReturnRequest extends FormRequest
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
            'order_item_id' => [
                'required',
                'integer',
                Rule::exists('order_items', 'id')->where(fn ($query): mixed => $query->where('order_id', $order?->id)),
            ],
            'quantity' => ['required', 'integer', 'min:1'],
            'condition' => ['required', 'string', Rule::in([OrderReturn::CONDITION_SELLABLE, OrderReturn::CONDITION_DAMAGED])],
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }
}
