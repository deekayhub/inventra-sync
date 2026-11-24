<?php

namespace App\Http\Requests\Inventory;

use App\Models\ProductCategory;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UpdateProductCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $category = $this->route('category');

        $this->merge([
            'description' => filled($this->input('description')) ? $this->input('description') : null,
            'slug' => filled($this->input('slug'))
                ? Str::slug((string) $this->input('slug'))
                : ProductCategory::makeSlug((string) ($this->input('name') ?? $category?->name ?? '')),
        ]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $tenantKey = (string) tenant()?->getTenantKey();
        $categoryId = $this->route('category')?->getKey();

        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'required',
                'string',
                'max:255',
                Rule::unique('product_categories', 'slug')
                    ->where(fn (Builder $query): Builder => $query->where('tenant_id', $tenantKey))
                    ->ignore($categoryId),
            ],
            'description' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
