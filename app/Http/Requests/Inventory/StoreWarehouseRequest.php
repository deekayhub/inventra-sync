<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWarehouseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'address' => filled($this->input('address')) ? $this->input('address') : null,
            'is_default' => $this->boolean('is_default'),
            'is_active' => $this->boolean('is_active', true),
        ]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $tenantKey = (string) tenant()?->getTenantKey();

        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => [
                'required',
                'string',
                'max:100',
                Rule::unique('warehouses', 'code')->where(fn (Builder $query): Builder => $query
                    ->where('tenant_id', $tenantKey)),
            ],
            'address' => ['nullable', 'string'],
            'is_default' => ['boolean'],
            'is_active' => ['boolean'],
        ];
    }
}
