<?php

namespace App\Actions\Warehouses;

use App\Models\Warehouse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateWarehouse
{
    public function __invoke(Warehouse $warehouse, array $data): Warehouse
    {
        return DB::transaction(function () use ($warehouse, $data): Warehouse {
            $isDefault = (bool) ($data['is_default'] ?? $warehouse->is_default);
            $isActive = (bool) ($data['is_active'] ?? $warehouse->is_active);

            if ($warehouse->is_default && (! $isDefault || ! $isActive)) {
                throw ValidationException::withMessages([
                    'is_default' => __('Set another active warehouse as the default before changing this one.'),
                ]);
            }

            if ($isDefault) {
                DB::table('warehouses')
                    ->where('tenant_id', $warehouse->tenant_id)
                    ->where('id', '!=', $warehouse->getKey())
                    ->where('is_default', true)
                    ->update([
                        'is_default' => false,
                        'default_marker' => null,
                        'updated_at' => now(),
                    ]);
            }

            $warehouse->update([
                'name' => $data['name'] ?? $warehouse->name,
                'code' => $data['code'] ?? $warehouse->code,
                'address' => array_key_exists('address', $data) ? $data['address'] : $warehouse->address,
                'is_default' => $isDefault,
                'default_marker' => $isDefault ? $warehouse->tenant_id.':default' : null,
                'is_active' => $isActive,
            ]);

            activity('warehouses')
                ->performedOn($warehouse)
                ->causedBy(auth()->user())
                ->event('updated')
                ->log('updated');

            return $warehouse;
        });
    }
}
