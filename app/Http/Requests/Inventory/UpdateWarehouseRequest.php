<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateWarehouseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $warehouse = $this->route('warehouse');

        $this->merge([
            'address' => filled($this->input('address')) ? $this->input('address') : null,
            'is_default' => $this->has('is_default')
                ? $this->boolean('is_default')
                : (bool) $warehouse?->is_default,
            'is_active' => $this->has('is_active')
                ? $this->boolean('is_active')
                : (bool) $warehouse?->is_active,
        ]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $tenantKey = (string) tenant()?->getTenantKey();
        $warehouseId = $this->route('warehouse')?->getKey();

        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => [
                'required',
                'string',
                'max:100',
                Rule::unique('warehouses', 'code')
                    ->where(fn (Builder $query): Builder => $query->where('tenant_id', $tenantKey))
                    ->ignore($warehouseId),
            ],
            'address' => ['nullable', 'string'],
            'is_default' => ['boolean'],
            'is_active' => ['boolean'],
        ];
    }
}
