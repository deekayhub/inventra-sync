<?php

use App\Models\Tenant;

test('tenant requests work with a cache store that does not support tags', function (): void {
    config()->set('cache.default', 'database');

    $tenant = Tenant::create([
        'name' => 'Cache Tenant',
        'email' => 'owner@cache.test',
    ]);
    $tenant->domains()->create(['domain' => 'cache-tenant']);

    $this->get('http://cache-tenant.saas.test/')->assertRedirect();
});
