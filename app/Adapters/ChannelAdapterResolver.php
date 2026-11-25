<?php

namespace App\Adapters;

use App\Contracts\ChannelAdapter;
use App\Models\ChannelConnection;
use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;

class ChannelAdapterResolver
{
    public function __construct(private Container $container) {}

    public function resolve(string $provider): ChannelAdapter
    {
        return match ($provider) {
            ChannelConnection::PROVIDER_SHOPIFY => $this->container->make(ShopifyChannelAdapter::class),
            ChannelConnection::PROVIDER_WOOCOMMERCE => $this->container->make(WooCommerceChannelAdapter::class),
            default => throw new InvalidArgumentException("Unsupported channel provider [{$provider}]."),
        };
    }
}
