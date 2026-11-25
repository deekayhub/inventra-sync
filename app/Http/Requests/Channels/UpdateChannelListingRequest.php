<?php

namespace App\Http\Requests\Channels;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateChannelListingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'paused' => $this->boolean('paused'),
        ]);
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'product_variant_id' => [
                'required',
                'integer',
                Rule::exists('product_variants', 'id')->where(
                    fn (Builder $query): Builder => $query->where('tenant_id', tenant()->getKey()),
                ),
            ],
            'buffer_stock' => ['required', 'integer', 'min:0', 'max:1000000'],
            'paused' => ['boolean'],
        ];
    }
}
