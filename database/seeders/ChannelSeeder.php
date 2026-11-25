<?php

namespace Database\Seeders;

use App\Models\ChannelConnection;
use App\Models\ChannelCredential;
use App\Models\ChannelInventorySetting;
use App\Models\ChannelListing;
use App\Models\ChannelProductMapping;
use App\Models\ProductVariant;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Crypt;

class ChannelSeeder extends Seeder
{
    use WithoutModelEvents;

    public const DUMMY_SHOPIFY_TOKEN = 'demo-shopify-access-token';

    public const DUMMY_WOOCOMMERCE_SECRET = 'demo-woocommerce-consumer-secret';

    public function run(): void
    {
        $tenantId = (string) tenant()->getTenantKey();
        $shopifyDomain = sprintf('demo-%s.myshopify.com', $tenantId);
        $wooCommerceUrl = sprintf('https://demo-%s.example.com', $tenantId);

        $shopify = $this->connection(
            $tenantId,
            ChannelConnection::PROVIDER_SHOPIFY,
            'Demo Shopify Store',
            $shopifyDomain,
        );
        $wooCommerce = $this->connection(
            $tenantId,
            ChannelConnection::PROVIDER_WOOCOMMERCE,
            'Demo WooCommerce Store',
            $wooCommerceUrl,
        );

        $this->credentials($tenantId, $shopify, [
            'shop_domain' => $shopifyDomain,
            'access_token' => self::DUMMY_SHOPIFY_TOKEN,
            'location_id' => '1',
        ]);
        $this->credentials($tenantId, $wooCommerce, [
            'site_url' => $wooCommerceUrl,
            'consumer_key' => 'demo-woocommerce-consumer-key',
            'consumer_secret' => self::DUMMY_WOOCOMMERCE_SECRET,
        ]);

        $variants = ProductVariant::query()
            ->whereIn('sku', ['DEMO-NOTE-A4', 'DEMO-COFFEE-250G', 'DEMO-MUG-BLACK'])
            ->get()
            ->keyBy('sku');

        $notebook = $this->listing($tenantId, $shopify, 'SEED-SHOPIFY-901', 'Demo Notebook (Shopify)');
        $coffee = $this->listing($tenantId, $shopify, 'SEED-SHOPIFY-902', 'Demo Coffee (Shopify)');
        $this->listing($tenantId, $shopify, 'SEED-SHOPIFY-903', 'Demo Pen (unmapped)');
        $mug = $this->listing($tenantId, $wooCommerce, 'SEED-WOO-77', 'Demo Mug (WooCommerce)');

        $this->mapListing($tenantId, $shopify, $notebook, $variants->get('DEMO-NOTE-A4'), 2, false);
        $this->mapListing($tenantId, $shopify, $coffee, $variants->get('DEMO-COFFEE-250G'), 3, true);
        $this->mapListing($tenantId, $wooCommerce, $mug, $variants->get('DEMO-MUG-BLACK'), 1, false);
    }

    private function connection(
        string $tenantId,
        string $provider,
        string $name,
        string $accountIdentifier,
    ): ChannelConnection {
        return ChannelConnection::query()->firstOrCreate(
            [
                'tenant_id' => $tenantId,
                'provider' => $provider,
                'account_identifier' => $accountIdentifier,
            ],
            [
                'name' => $name,
                'status' => ChannelConnection::STATUS_ACTIVE,
                'activated_at' => now(),
                'last_synced_at' => now()->subMinutes(5),
            ],
        );
    }

    /** @param array<string, string> $credentials */
    private function credentials(string $tenantId, ChannelConnection $connection, array $credentials): void
    {
        foreach ($credentials as $key => $value) {
            ChannelCredential::query()->firstOrCreate(
                [
                    'tenant_id' => $tenantId,
                    'channel_connection_id' => $connection->id,
                    'key' => $key,
                ],
                [
                    'encrypted_value' => Crypt::encryptString($value),
                ],
            );
        }
    }

    private function listing(
        string $tenantId,
        ChannelConnection $connection,
        string $externalId,
        string $title,
    ): ChannelListing {
        return ChannelListing::query()->firstOrCreate(
            [
                'tenant_id' => $tenantId,
                'channel_connection_id' => $connection->id,
                'external_id' => $externalId,
            ],
            [
                'title' => $title,
                'status' => ChannelListing::STATUS_ACTIVE,
                'last_imported_at' => now(),
            ],
        );
    }

    private function mapListing(
        string $tenantId,
        ChannelConnection $connection,
        ChannelListing $listing,
        ?ProductVariant $variant,
        int $bufferStock,
        bool $paused,
    ): void {
        if (! $variant instanceof ProductVariant) {
            return;
        }

        $mapping = ChannelProductMapping::query()->firstOrCreate(
            [
                'tenant_id' => $tenantId,
                'channel_listing_id' => $listing->id,
            ],
            [
                'channel_connection_id' => $connection->id,
                'product_variant_id' => $variant->id,
            ],
        );

        ChannelInventorySetting::query()->firstOrCreate(
            [
                'tenant_id' => $tenantId,
                'channel_product_mapping_id' => $mapping->id,
            ],
            [
                'buffer_stock' => $bufferStock,
                'paused' => $paused,
            ],
        );
    }
}
