<?php

namespace App\Actions\Warehouses;

use App\Models\Warehouse;
use Illuminate\Support\Facades\DB;

class CreateWarehouse
{
    public function __invoke(array $data): Warehouse
    {
        return DB::transaction(function () use ($data): Warehouse {
            $tenantId = (string) tenant()->getTenantKey();
            $isDefault = (bool) ($data['is_default'] ?? false);

            if ($isDefault) {
                DB::table('warehouses')
                    ->where('tenant_id', $tenantId)
                    ->where('is_default', true)
                    ->update([
                        'is_default' => false,
                        'default_marker' => null,
                        'updated_at' => now(),
                    ]);
            }

            $warehouse = Warehouse::create([
                'name' => $data['name'],
                'code' => $data['code'],
                'address' => $data['address'] ?? null,
                'is_default' => $isDefault,
                'default_marker' => $isDefault ? $tenantId.':default' : null,
                'is_active' => $data['is_active'] ?? true,
            ]);

            activity('warehouses')
                ->performedOn($warehouse)
                ->causedBy(auth()->user())
                ->event('created')
                ->log('created');

            return $warehouse;
        });
    }
}
