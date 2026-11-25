<?php

namespace App\Http\Controllers\Channels;

use App\Actions\Channels\ConnectChannel;
use App\Actions\Channels\ImportChannelListings;
use App\Http\Controllers\Controller;
use App\Http\Requests\Channels\StoreChannelConnectionRequest;
use App\Models\ChannelConnection;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class ChannelConnectionController extends Controller
{
    public function index(): View
    {
        $connections = ChannelConnection::query()
            ->withCount(['listings', 'mappings'])
            ->latest()
            ->paginate(15);

        return view('tenant.channels.index', compact('connections'));
    }

    public function create(): View
    {
        return view('tenant.channels.create', [
            'providers' => [
                ChannelConnection::PROVIDER_SHOPIFY => __('Shopify'),
                ChannelConnection::PROVIDER_WOOCOMMERCE => __('WooCommerce'),
            ],
        ]);
    }

    public function store(StoreChannelConnectionRequest $request, ConnectChannel $connectChannel): RedirectResponse
    {
        $connectChannel($request->validated());

        return redirect()
            ->route('channels.index')
            ->with('status', __('Channel connected successfully.'));
    }

    public function import(ChannelConnection $channel, ImportChannelListings $importListings): RedirectResponse
    {
        $imported = $importListings($channel);

        return redirect()
            ->route('channels.listings.index', ['channel_id' => $channel->id])
            ->with('status', __(':count listing(s) imported.', ['count' => $imported]));
    }

    public function deactivate(ChannelConnection $channel): RedirectResponse
    {
        $channel->forceFill(['status' => ChannelConnection::STATUS_INACTIVE])->save();

        activity('channels')
            ->performedOn($channel)
            ->causedBy(auth()->user())
            ->event('deactivated')
            ->log('deactivated');

        return redirect()
            ->route('channels.index')
            ->with('status', __('Channel connection deactivated.'));
    }
}
