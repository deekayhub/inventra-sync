<?php

namespace App\Jobs;

use App\Models\ChannelConnection;
use App\Models\InventoryMovement;
use App\Models\InventoryStock;
use App\Models\Order;
use App\Models\ReconciliationItem;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

class ExportReport implements ShouldQueue
{
    use InitializesActiveTenant, Queueable;

    public const REPORTS = [
        'current-inventory',
        'low-stock',
        'out-of-stock',
        'movements',
        'orders-by-channel',
        'channels',
        'reconciliation',
    ];

    public function __construct(
        private readonly string $tenantId,
        private readonly int $userId,
        private readonly string $report,
        private readonly string $fileName,
        private readonly array $filters = [],
    ) {}

    public function handle(): void
    {
        if (! $this->initializeActiveTenant($this->tenantId)) {
            return;
        }

        try {
            $handle = fopen('php://temp', 'r+');

            if ($handle === false) {
                return;
            }

            fputcsv($handle, $this->headers());
            $this->writeRows($handle);

            rewind($handle);
            Storage::disk('local')->put('report-exports/'.$this->fileName, stream_get_contents($handle));
            fclose($handle);

            activity('reports')
                ->causedBy(User::query()->find($this->userId))
                ->event('exported')
                ->withProperties(['file' => $this->fileName, 'report' => $this->report])
                ->log('exported report');
        } finally {
            tenancy()->end();
        }
    }

    private function headers(): array
    {
        return match ($this->report) {
            'current-inventory', 'low-stock', 'out-of-stock' => [
                'SKU', 'Product', 'Warehouse', 'On hand', 'Reserved', 'Available', 'Channel available', 'Threshold',
            ],
            'movements' => [
                'Recorded', 'Movement', 'SKU', 'Warehouse', 'Quantity change', 'Quantity after', 'Reason', 'Reference', 'Actor',
            ],
            'orders-by-channel' => [
                'Channel', 'Provider', 'Status', 'Orders', 'Fulfilled', 'Revenue',
            ],
            'channels' => [
                'Channel', 'Provider', 'Status', 'Listings', 'Mapped variants', 'Last sync',
            ],
            'reconciliation' => [
                'Run', 'Channel', 'Listing', 'SKU', 'Status', 'Expected', 'Remote', 'Difference', 'Correction', 'Corrected at',
            ],
            default => throw new InvalidArgumentException('Unsupported report export.'),
        };
    }

    private function writeRows($handle): void
    {
        match ($this->report) {
            'current-inventory' => $this->writeStockRows($handle, false, false),
            'low-stock' => $this->writeStockRows($handle, true, false),
            'out-of-stock' => $this->writeStockRows($handle, false, true),
            'movements' => $this->writeMovementRows($handle),
            'orders-by-channel' => $this->writeOrderRows($handle),
            'channels' => $this->writeChannelRows($handle),
            'reconciliation' => $this->writeReconciliationRows($handle),
            default => throw new InvalidArgumentException('Unsupported report export.'),
        };
    }

    private function writeStockRows($handle, bool $lowOnly, bool $outOnly): void
    {
        InventoryStock::query()
            ->with(['warehouse:id,name', 'variant:id,sku,product_id', 'variant.product:id,name'])
            ->when($this->filter('warehouse_id') !== null, fn ($query) => $query->where('warehouse_id', $this->filter('warehouse_id')))
            ->when($lowOnly, fn ($query) => $query
                ->whereRaw('(on_hand - reserved) <= low_stock_threshold')
                ->whereRaw('(on_hand - reserved) > 0'))
            ->when($outOnly, fn ($query) => $query->whereRaw('(on_hand - reserved) <= 0'))
            ->when($this->filter('search') !== '', function ($query): void {
                $query->whereHas('variant', fn ($variantQuery) => $variantQuery
                    ->where('sku', 'like', '%'.$this->filter('search').'%')
                    ->orWhere('title', 'like', '%'.$this->filter('search').'%'));
            })
            ->orderBy('id')
            ->chunkById(100, function ($stocks) use ($handle): void {
                foreach ($stocks as $stock) {
                    fputcsv($handle, [
                        $stock->variant?->sku,
                        $stock->variant?->product?->name,
                        $stock->warehouse?->name,
                        $stock->on_hand,
                        $stock->reserved,
                        $stock->available_stock,
                        $stock->channel_available_stock,
                        $stock->low_stock_threshold,
                    ]);
                }
            });
    }

    private function writeMovementRows($handle): void
    {
        InventoryMovement::query()
            ->with(['warehouse:id,name', 'variant:id,sku', 'actor:id,name'])
            ->when($this->filter('warehouse_id') !== null, fn ($query) => $query->where('warehouse_id', $this->filter('warehouse_id')))
            ->when($this->filter('movement_type') !== '', fn ($query) => $query->where('movement_type', $this->filter('movement_type')))
            ->when($this->fromDate() !== null, fn ($query) => $query->where('created_at', '>=', $this->fromDate()))
            ->when($this->toDate() !== null, fn ($query) => $query->where('created_at', '<=', $this->toDate()))
            ->orderBy('id')
            ->chunkById(100, function ($movements) use ($handle): void {
                foreach ($movements as $movement) {
                    fputcsv($handle, [
                        $movement->created_at?->toDateTimeString(),
                        $movement->movement_type,
                        $movement->variant?->sku,
                        $movement->warehouse?->name,
                        $movement->quantity_change,
                        $movement->quantity_after,
                        $movement->reason,
                        $movement->reference,
                        $movement->actor?->name,
                    ]);
                }
            });
    }

    private function writeOrderRows($handle): void
    {
        $orders = Order::query()
            ->with('connection:id,name,provider,status')
            ->select('channel_connection_id')
            ->selectRaw('COUNT(*) as orders_count')
            ->selectRaw('COALESCE(SUM(CASE WHEN status = ? THEN 1 ELSE 0 END), 0) as fulfilled_count', [Order::STATUS_FULFILLED])
            ->selectRaw('COALESCE(SUM(total_amount), 0) as revenue')
            ->when($this->filter('status') !== '', fn ($query) => $query->where('status', $this->filter('status')))
            ->when($this->fromDate() !== null, fn ($query) => $query->where('ordered_at', '>=', $this->fromDate()))
            ->when($this->toDate() !== null, fn ($query) => $query->where('ordered_at', '<=', $this->toDate()))
            ->groupBy('channel_connection_id')
            ->orderByDesc('orders_count')
            ->get();

        foreach ($orders as $order) {
            fputcsv($handle, [
                $order->connection?->name,
                $order->connection?->provider,
                $order->connection?->status,
                $order->orders_count,
                $order->fulfilled_count,
                $order->revenue,
            ]);
        }
    }

    private function writeChannelRows($handle): void
    {
        ChannelConnection::query()
            ->withCount(['listings', 'mappings'])
            ->when($this->filter('status') !== '', fn ($query) => $query->where('status', $this->filter('status')))
            ->when($this->filter('provider') !== '', fn ($query) => $query->where('provider', $this->filter('provider')))
            ->when($this->filter('search') !== '', fn ($query) => $query
                ->where(function ($query): void {
                    $search = '%'.$this->filter('search').'%';
                    $query
                        ->where('name', 'like', $search)
                        ->orWhere('account_identifier', 'like', $search);
                }))
            ->orderBy('id')
            ->chunkById(100, function ($connections) use ($handle): void {
                foreach ($connections as $connection) {
                    fputcsv($handle, [
                        $connection->name,
                        $connection->provider,
                        $connection->status,
                        $connection->listings_count,
                        $connection->mappings_count,
                        $connection->last_synced_at?->toDateTimeString(),
                    ]);
                }
            });
    }

    private function writeReconciliationRows($handle): void
    {
        ReconciliationItem::query()
            ->with(['run:id,channel_connection_id,created_at', 'connection:id,name', 'listing:id,title', 'variant:id,sku'])
            ->when($this->filter('status') !== '', fn ($query) => $query->where('status', $this->filter('status')))
            ->when($this->fromDate() !== null, fn ($query) => $query->where('created_at', '>=', $this->fromDate()))
            ->when($this->toDate() !== null, fn ($query) => $query->where('created_at', '<=', $this->toDate()))
            ->orderBy('id')
            ->chunkById(100, function ($items) use ($handle): void {
                foreach ($items as $item) {
                    fputcsv($handle, [
                        $item->run?->created_at?->toDateTimeString(),
                        $item->connection?->name,
                        $item->listing?->title,
                        $item->variant?->sku,
                        $item->status,
                        $item->expected_quantity,
                        $item->remote_quantity,
                        $item->difference,
                        $item->correction_quantity,
                        $item->corrected_at?->toDateTimeString(),
                    ]);
                }
            });
    }

    private function filter(string $key): string
    {
        return trim((string) ($this->filters[$key] ?? ''));
    }

    private function fromDate(): ?Carbon
    {
        $value = $this->filter('from');

        return $value === '' ? null : Carbon::parse($value)->startOfDay();
    }

    private function toDate(): ?Carbon
    {
        $value = $this->filter('to');

        return $value === '' ? null : Carbon::parse($value)->endOfDay();
    }
}
