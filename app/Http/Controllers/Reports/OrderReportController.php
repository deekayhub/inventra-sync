<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OrderReportController extends Controller
{
    public function byChannel(Request $request): View
    {
        $status = trim((string) $request->query('status', ''));
        $from = $request->date('from')?->startOfDay();
        $to = $request->date('to')?->endOfDay();

        $base = Order::query()
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->when($from, fn ($query) => $query->where('ordered_at', '>=', $from))
            ->when($to, fn ($query) => $query->where('ordered_at', '<=', $to));

        $summary = (clone $base)
            ->toBase()
            ->selectRaw('COUNT(*) as orders_count')
            ->selectRaw('COALESCE(SUM(total_amount), 0) as revenue')
            ->selectRaw('COALESCE(SUM(CASE WHEN status = ? THEN 1 ELSE 0 END), 0) as fulfilled_count', [Order::STATUS_FULFILLED])
            ->first();

        $channels = (clone $base)
            ->with('connection:id,name,provider,status')
            ->select('channel_connection_id')
            ->selectRaw('COUNT(*) as orders_count')
            ->selectRaw('COALESCE(SUM(total_amount), 0) as revenue')
            ->selectRaw('COALESCE(SUM(CASE WHEN status = ? THEN 1 ELSE 0 END), 0) as fulfilled_count', [Order::STATUS_FULFILLED])
            ->groupBy('channel_connection_id')
            ->orderByDesc('orders_count')
            ->paginate(20)
            ->withQueryString();

        return view('tenant.reports.orders.by-channel', [
            'channels' => $channels,
            'summary' => $summary,
            'status' => $status,
            'statuses' => [
                Order::STATUS_PENDING => __('Pending'),
                Order::STATUS_CONFIRMED => __('Confirmed'),
                Order::STATUS_PROCESSING => __('Processing'),
                Order::STATUS_PARTIALLY_FULFILLED => __('Partially fulfilled'),
                Order::STATUS_FULFILLED => __('Fulfilled'),
                Order::STATUS_CANCELLED => __('Cancelled'),
                Order::STATUS_FAILED => __('Failed'),
            ],
            'from' => $from?->toDateString(),
            'to' => $to?->toDateString(),
        ]);
    }
}
