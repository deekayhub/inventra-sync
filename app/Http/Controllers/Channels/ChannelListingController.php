<?php

namespace App\Http\Controllers\Channels;

use App\Actions\Channels\MapChannelListing;
use App\Actions\Channels\QueueChannelInventorySync;
use App\Http\Controllers\Controller;
use App\Http\Requests\Channels\UpdateChannelListingRequest;
use App\Models\ChannelConnection;
use App\Models\ChannelListing;
use App\Models\ProductVariant;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class ChannelListingController extends Controller
{
    public function index(): View
    {
        $connections = ChannelConnection::query()->orderBy('name')->get();
        $selectedChannelId = (int) request()->query('channel_id', 0);

        $listings = ChannelListing::query()
            ->with(['connection', 'mapping.variant.product', 'inventorySetting'])
            ->when($selectedChannelId > 0, fn ($query) => $query->where('channel_connection_id', $selectedChannelId))
            ->latest('last_imported_at')
            ->paginate(20)
            ->withQueryString();

        return view('tenant.channels.listings.index', compact('connections', 'listings', 'selectedChannelId'));
    }

    public function edit(ChannelListing $listing): View
    {
        $variants = ProductVariant::query()
            ->with('product')
            ->orderBy('sku')
            ->get()
            ->mapWithKeys(fn (ProductVariant $variant): array => [
                $variant->id => trim(($variant->product?->name ?? __('Unknown product')).' — '.($variant->sku ?: '#'.$variant->id)),
            ]);

        $mapping = $listing->mapping;
        $setting = $mapping?->inventorySetting;

        return view('tenant.channels.listings.edit', [
            'listing' => $listing,
            'variants' => $variants,
            'mapping' => $mapping,
            'setting' => $setting,
        ]);
    }

    public function update(UpdateChannelListingRequest $request, ChannelListing $listing, MapChannelListing $mapListing): RedirectResponse
    {
        $mapListing($listing, $request->validated());

        return redirect()
            ->route('channels.listings.index')
            ->with('status', __('Listing mapping saved.'));
    }

    public function sync(QueueChannelInventorySync $queueChannelInventorySync): RedirectResponse
    {
        $connections = ChannelConnection::query()->active()->get();
        $synced = 0;

        foreach ($connections as $connection) {
            $synced += $queueChannelInventorySync($connection);
        }

        return redirect()
            ->route('channels.listings.index')
            ->with('status', $connections->isEmpty()
                ? __('No active channel connections to synchronize.')
                : __(':count listing(s) queued for synchronization.', ['count' => $synced]));
    }
}
