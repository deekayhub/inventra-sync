<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReserveStockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'quantity' => filled($this->input('quantity')) ? (int) $this->input('quantity') : null,
            'expires_at' => filled($this->input('expires_at')) ? $this->input('expires_at') : null,
        ]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $tenantKey = (string) tenant()?->getTenantKey();

        return [
            'warehouse_id' => [
                'required',
                'integer',
                Rule::exists('warehouses', 'id')->where(fn (Builder $query): Builder => $query
                    ->where('tenant_id', $tenantKey)),
            ],
            'product_variant_id' => [
                'required',
                'integer',
                Rule::exists('product_variants', 'id')->where(fn (Builder $query): Builder => $query
                    ->where('tenant_id', $tenantKey)),
            ],
            'quantity' => ['required', 'integer', 'min:1'],
            'external_reference' => ['required', 'string', 'max:255'],
            'expires_at' => ['nullable', 'date', 'after_or_equal:now'],
        ];
    }
}
