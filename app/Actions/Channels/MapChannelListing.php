<?php

namespace App\Actions\Channels;

use App\Models\ChannelInventorySetting;
use App\Models\ChannelListing;
use App\Models\ChannelProductMapping;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MapChannelListing
{
    /** @param array{product_variant_id: int|string, buffer_stock?: int|string, paused?: bool} $data */
    public function __invoke(ChannelListing $listing, array $data): ChannelProductMapping
    {
        $variantId = (int) $data['product_variant_id'];

        return DB::transaction(function () use ($listing, $data, $variantId): ChannelProductMapping {
            ProductVariant::query()->findOrFail($variantId);

            $existing = $listing->mapping()->first();

            if ($existing !== null && $existing->product_variant_id !== $variantId) {
                throw ValidationException::withMessages([
                    'product_variant_id' => __('This listing is already mapped to another variant.'),
                ]);
            }

            $variantConflict = ChannelProductMapping::query()
                ->where('channel_connection_id', $listing->channel_connection_id)
                ->where('product_variant_id', $variantId)
                ->when($existing !== null, fn ($query) => $query->whereKeyNot($existing->getKey()))
                ->exists();

            if ($variantConflict) {
                throw ValidationException::withMessages([
                    'product_variant_id' => __('That variant is already mapped to another listing on this channel.'),
                ]);
            }

            $mapping = ChannelProductMapping::updateOrCreate(
                ['channel_listing_id' => $listing->id],
                [
                    'channel_connection_id' => $listing->channel_connection_id,
                    'product_variant_id' => $variantId,
                ],
            );

            ChannelInventorySetting::updateOrCreate(
                ['channel_product_mapping_id' => $mapping->id],
                [
                    'buffer_stock' => (int) ($data['buffer_stock'] ?? 0),
                    'paused' => (bool) ($data['paused'] ?? false),
                ],
            );

            activity('channels')
                ->performedOn($listing)
                ->causedBy(auth()->user())
                ->event('mapped')
                ->log('mapped');

            return $mapping;
        });
    }
}
