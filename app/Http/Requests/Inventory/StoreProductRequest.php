<?php

namespace App\Http\Requests\Inventory;

use App\Models\Product;
use Closure;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $variants = collect($this->input('variants', []))
            ->map(fn (mixed $variant): array => array_merge(
                is_array($variant) ? $variant : [],
                ['sku' => filled($variant['sku'] ?? null) ? Str::upper(trim((string) $variant['sku'])) : null],
            ))
            ->values()
            ->all();

        $this->merge([
            'product_category_id' => filled($this->input('product_category_id')) ? $this->input('product_category_id') : null,
            'variants' => $variants,
        ]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $tenantKey = (string) tenant()?->getTenantKey();

        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'product_category_id' => [
                'nullable',
                'integer',
                Rule::exists('product_categories', 'id')->where(fn (Builder $query): Builder => $query
                    ->where('tenant_id', $tenantKey)),
            ],
            'type' => ['required', Rule::in([Product::TYPE_SIMPLE, Product::TYPE_VARIABLE])],
            'status' => ['required', Rule::in([Product::STATUS_DRAFT, Product::STATUS_ACTIVE, Product::STATUS_ARCHIVED])],
            'low_stock_threshold' => ['required', 'integer', 'min:0', 'max:100000'],
            'buffer_stock' => ['required', 'integer', 'min:0', 'max:100000'],
            'variants' => ['required', 'array', 'min:1'],
            'variants.*.sku' => [
                'required',
                'string',
                'max:100',
                function (string $attribute, mixed $value, Closure $fail) use ($tenantKey): void {
                    $exists = DB::table('product_variants')
                        ->where('tenant_id', $tenantKey)
                        ->where('sku', $value)
                        ->exists();

                    if ($exists) {
                        $fail(__('This SKU is already used in this workspace.'));
                    }
                },
            ],
            'variants.*.barcode' => ['nullable', 'string', 'max:100'],
            'variants.*.title' => ['nullable', 'string', 'max:255'],
            'variants.*.variant_attributes' => ['nullable', 'array'],
            'variants.*.sort_order' => ['nullable', 'integer', 'min:0'],
            'variants.*.is_primary' => ['nullable', 'boolean'],
            'variants.*.status' => ['nullable', Rule::in(['active', 'inactive'])],
        ];
    }

    /** @return list<Closure> */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $skus = collect($this->input('variants', []))
                    ->pluck('sku')
                    ->filter()
                    ->map(fn (mixed $sku): string => Str::upper((string) $sku));

                if ($skus->count() !== $skus->unique()->count()) {
                    $validator->errors()->add('variants', __('Each SKU must be unique within the product.'));
                }
            },
        ];
    }
}
