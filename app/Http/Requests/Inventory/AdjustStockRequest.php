<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdjustStockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'quantity' => filled($this->input('quantity')) ? (int) $this->input('quantity') : null,
            'reference' => filled($this->input('reference')) ? $this->input('reference') : null,
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
            'quantity' => ['required', 'integer', 'not_in:0'],
            'idempotency_key' => ['required', 'string', 'max:255'],
            'reason' => ['required', 'string', 'max:255'],
            'reference' => ['nullable', 'string', 'max:255'],
        ];
    }
}
