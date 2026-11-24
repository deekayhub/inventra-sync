<?php

namespace App\Http\Requests\Inventory;

use App\Models\ProductCategory;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreProductCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'description' => filled($this->input('description')) ? $this->input('description') : null,
            'slug' => filled($this->input('slug'))
                ? Str::slug((string) $this->input('slug'))
                : ProductCategory::makeSlug((string) $this->input('name', '')),
        ]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $tenantKey = (string) tenant()?->getTenantKey();

        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'required',
                'string',
                'max:255',
                Rule::unique('product_categories', 'slug')->where(fn (Builder $query): Builder => $query
                    ->where('tenant_id', $tenantKey)),
            ],
            'description' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
