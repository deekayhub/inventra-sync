<?php

namespace App\Jobs;

use App\Models\Tenant;

trait InitializesActiveTenant
{
    private function initializeActiveTenant(string $tenantId): bool
    {
        $tenant = Tenant::findOrFail($tenantId);

        if ($tenant->status !== Tenant::STATUS_ACTIVE) {
            return false;
        }

        tenancy()->initialize($tenant);

        return true;
    }
}
