<?php

namespace App\Http\Controllers;

use App\Models\InventoryOutboxEvent;
use App\Models\InventoryStock;
use App\Models\ReconciliationItem;
use App\Models\Role;
use App\Models\Scopes\TenantRoleScope;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Spatie\Activitylog\Models\Activity;

class DashboardController extends Controller
{
    /**
     * Display the dashboard with metrics and recent activity for the current group.
     */
    public function index(Request $request): View
    {
        $user = $request->user();
        $tenant = tenant();
        $tenantKey = $user->tenant_id;
        $today = now();

        $tenantCauser = function (Builder $query) use ($tenantKey): void {
            $query->where('tenant_id', $tenantKey);
        };

        $memberStats = User::query()
            ->where('tenant_id', $tenantKey)
            ->toBase()
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('COALESCE(SUM(email_verified_at IS NOT NULL), 0) as verified')
            ->first();

        $roleCount = Role::withoutGlobalScope(TenantRoleScope::class)
            ->where('tenant_id', $tenantKey)
            ->count();
        $domainCount = $tenant?->domains()->count() ?? 0;

        $tenantKpis = [];

        if ($tenant !== null) {
            $stockSummary = InventoryStock::query()
                ->toBase()
                ->selectRaw('COALESCE(SUM(CASE WHEN on_hand - reserved <= low_stock_threshold AND on_hand - reserved > 0 THEN 1 ELSE 0 END), 0) as low_stock_count')
                ->selectRaw('COALESCE(SUM(CASE WHEN on_hand - reserved <= 0 THEN 1 ELSE 0 END), 0) as out_of_stock_count')
                ->first();

            $syncIssuesCount = InventoryOutboxEvent::query()
                ->whereIn('status', [InventoryOutboxEvent::STATUS_FAILED, InventoryOutboxEvent::STATUS_RETRYING])
                ->count();

            $openDiscrepanciesCount = ReconciliationItem::query()
                ->whereIn('status', [ReconciliationItem::STATUS_DISCREPANCY, ReconciliationItem::STATUS_FAILED])
                ->count();

            $tenantKpis = [
                [
                    'label' => __('Low stock'),
                    'value' => (int) $stockSummary->low_stock_count,
                    'hint' => __('Rows at or below threshold'),
                    'icon' => 'alert',
                    'href' => route('reports.inventory.low-stock'),
                ],
                [
                    'label' => __('Out of stock'),
                    'value' => (int) $stockSummary->out_of_stock_count,
                    'hint' => __('Rows with no available stock'),
                    'icon' => 'box',
                    'href' => route('reports.inventory.out-of-stock'),
                ],
                [
                    'label' => __('Sync issues'),
                    'value' => $syncIssuesCount,
                    'hint' => __('Failed and retrying pushes'),
                    'icon' => 'sync',
                    'href' => route('reports.failures.index'),
                ],
                [
                    'label' => __('Open discrepancies'),
                    'value' => $openDiscrepanciesCount,
                    'hint' => __('Reconciliation items to review'),
                    'icon' => 'chart',
                    'href' => route('reports.reconciliation.index'),
                ],
            ];
        }

        $loginActivity = Activity::query()
            ->inLog('authentication')
            ->where('event', 'login')
            ->whereHas('causer', $tenantCauser);

        $signIns30Days = (clone $loginActivity)
            ->where('created_at', '>=', $today->copy()->subDays(30))
            ->count();

        $dailyLogins = (clone $loginActivity)
            ->where('created_at', '>=', $today->copy()->subDays(6)->startOfDay())
            ->toBase()
            ->selectRaw('DATE(created_at) as day, COUNT(*) as total')
            ->groupBy('day')
            ->orderBy('day')
            ->pluck('total', 'day');

        $loginTrend = collect(range(6, 0))
            ->mapWithKeys(function (int $daysAgo) use ($today, $dailyLogins): array {
                $date = $today->copy()->subDays($daysAgo)->toDateString();

                return [$date => (int) ($dailyLogins[$date] ?? 0)];
            });

        $teamMembers = User::query()
            ->where('tenant_id', $tenantKey)
            ->with('roles:id,name')
            ->latest()
            ->limit(5)
            ->get(['id', 'name', 'email', 'email_verified_at', 'created_at']);

        $recentActivity = Activity::query()
            ->inLog('authentication')
            ->whereHas('causer', $tenantCauser)
            ->with('causer')
            ->latest()
            ->orderByDesc('id')
            ->limit(5)
            ->get();

        return view('dashboard', [
            'recentActivity' => $recentActivity,
            'memberStats' => $memberStats,
            'roleCount' => $roleCount,
            'domainCount' => $domainCount,
            'signIns30Days' => $signIns30Days,
            'tenantKpis' => $tenantKpis,
            'loginTrend' => $loginTrend,
            'chartMax' => max(1, (int) $loginTrend->max()),
            'teamMembers' => $teamMembers,
        ]);
    }
}
