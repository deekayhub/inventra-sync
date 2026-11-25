<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\ChannelConnection;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ChannelReportController extends Controller
{
    public function index(Request $request): View
    {
        $status = trim((string) $request->query('status', ''));
        $provider = trim((string) $request->query('provider', ''));
        $search = trim((string) $request->query('search', ''));

        $base = ChannelConnection::query()
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->when($provider !== '', fn ($query) => $query->where('provider', $provider))
            ->when($search !== '', fn ($query) => $query
                ->where(function ($query) use ($search): void {
                    $query
                        ->where('name', 'like', '%'.$search.'%')
                        ->orWhere('account_identifier', 'like', '%'.$search.'%');
                }));

        $summary = (clone $base)
            ->toBase()
            ->selectRaw('COUNT(*) as connection_count')
            ->selectRaw('COALESCE(SUM(status = ?), 0) as active_count', [ChannelConnection::STATUS_ACTIVE])
            ->selectRaw('COALESCE(SUM(last_synced_at IS NULL), 0) as never_synced_count')
            ->first();

        $connections = (clone $base)
            ->with('latestSyncLog')
            ->withCount(['listings', 'mappings'])
            ->orderByDesc('last_synced_at')
            ->orderBy('id')
            ->paginate(20)
            ->withQueryString();

        return view('tenant.reports.channels.index', [
            'connections' => $connections,
            'summary' => $summary,
            'status' => $status,
            'provider' => $provider,
            'search' => $search,
            'statuses' => [
                ChannelConnection::STATUS_PENDING => __('Pending'),
                ChannelConnection::STATUS_ACTIVE => __('Active'),
                ChannelConnection::STATUS_INACTIVE => __('Inactive'),
            ],
            'providers' => [
                ChannelConnection::PROVIDER_SHOPIFY => __('Shopify'),
                ChannelConnection::PROVIDER_WOOCOMMERCE => __('WooCommerce'),
            ],
        ]);
    }
}
