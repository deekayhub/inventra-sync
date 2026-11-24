<?php

namespace App\Actions\Warehouses;

use App\Models\Warehouse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeleteWarehouse
{
    public function __invoke(Warehouse $warehouse): void
    {
        DB::transaction(function () use ($warehouse): void {
            if ($warehouse->is_default) {
                throw ValidationException::withMessages([
                    'warehouse' => __('The default warehouse cannot be deleted.'),
                ]);
            }

            if ($warehouse->stocks()->exists() || $warehouse->movements()->exists()) {
                throw ValidationException::withMessages([
                    'warehouse' => __('This warehouse cannot be deleted because it contains stock or transaction history.'),
                ]);
            }

            activity('warehouses')
                ->performedOn($warehouse)
                ->causedBy(auth()->user())
                ->event('deleted')
                ->log('deleted');

            $warehouse->delete();
        });
    }
}
