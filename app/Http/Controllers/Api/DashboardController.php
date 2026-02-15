<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DeliveryOrder;
use App\Models\Inbound;
use App\Models\ItemStock;
use App\Models\StockMovement;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /**
     * Dashboard summary endpoint.
     *
     * GET /api/dashboard
     */
    public function index(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'as_of' => now()->toIso8601String(),
                'kpi' => $this->getKpi(),
                'weekly_movement' => $this->getWeeklyMovement(),
                'expiring_items' => $this->getExpiringItems(),
                'urgent_tasks' => $this->getUrgentTasks(),
            ],
        ]);
    }

    /**
     * KPI summary cards.
     */
    private function getKpi(): array
    {
        $totalInventory = ItemStock::sum('quantity');

        // Inventory change this week (sum of IN minus sum of OUT)
        $startOfWeek = now()->startOfWeek();
        $weekInbound = StockMovement::where('movement_type', StockMovement::TYPE_IN)
            ->where('created_at', '>=', $startOfWeek)
            ->sum('quantity');
        $weekOutbound = StockMovement::where('movement_type', StockMovement::TYPE_OUT)
            ->where('created_at', '>=', $startOfWeek)
            ->sum('quantity');
        $weekChange = $weekInbound - $weekOutbound;
        $changeSign = $weekChange >= 0 ? '+' : '';
        $inventoryChange = "{$changeSign}{$weekChange} this week";

        // Pending inbound (pending + verifying)
        $pendingInbound = Inbound::whereIn('status', [
            Inbound::STATUS_PENDING,
            Inbound::STATUS_VERIFYING,
        ])->count();

        // Pending inbound arriving today
        $pendingInboundToday = Inbound::whereIn('status', [
            Inbound::STATUS_PENDING,
            Inbound::STATUS_VERIFYING,
        ])->whereDate('expected_arrival_date', today())->count();

        // To dispatch (orders still in pipeline)
        $toDispatch = DeliveryOrder::whereNotIn('status', [
            DeliveryOrder::STATUS_DISPATCHED,
            DeliveryOrder::STATUS_COMPLETED,
        ])->count();

        // Urgent orders (due within 3 days, not dispatched/completed)
        $urgentOrders = DeliveryOrder::whereNotNull('due_date')
            ->whereDate('due_date', '<=', now()->addDays(3))
            ->whereNotIn('status', [
                DeliveryOrder::STATUS_DISPATCHED,
                DeliveryOrder::STATUS_COMPLETED,
            ])->count();

        return [
            'total_inventory' => (int) $totalInventory,
            'inventory_change' => $inventoryChange,
            'pending_inbound' => $pendingInbound,
            'pending_inbound_today' => $pendingInboundToday,
            'to_dispatch' => $toDispatch,
            'urgent_orders' => $urgentOrders,
        ];
    }

    /**
     * Weekly stock movement chart data (last 7 days).
     */
    private function getWeeklyMovement(): array
    {
        $days = collect();
        for ($i = 6; $i >= 0; $i--) {
            $days->push(now()->subDays($i)->startOfDay());
        }

        // Get all movements for the last 7 days in one query
        $movements = StockMovement::select(
                DB::raw('DATE(created_at) as date'),
                'movement_type',
                DB::raw('SUM(quantity) as total')
            )
            ->where('created_at', '>=', $days->first())
            ->groupBy(DB::raw('DATE(created_at)'), 'movement_type')
            ->get()
            ->groupBy('date');

        return $days->map(function (Carbon $day) use ($movements) {
            $dateKey = $day->toDateString();
            $dayMovements = $movements->get($dateKey, collect());

            return [
                'name' => $day->format('D'),
                'date' => $dateKey,
                'inbound' => (int) $dayMovements->where('movement_type', StockMovement::TYPE_IN)->sum('total'),
                'outbound' => (int) $dayMovements->where('movement_type', StockMovement::TYPE_OUT)->sum('total'),
            ];
        })->values()->toArray();
    }

    /**
     * Items expiring within 90 days.
     *
     * ≤ 30 days = critical
     * 31–90 days = warning
     */
    private function getExpiringItems(): array
    {
        $stocks = ItemStock::where('quantity', '>', 0)
            ->whereNotNull('expiry_date')
            ->where('expiry_date', '>', now())
            ->where('expiry_date', '<=', now()->addDays(90))
            ->with([
                'item:id,item_sku,item_name',
                'warehouse:id,name',
                'bin:id,code',
            ])
            ->orderBy('expiry_date')
            ->get();

        return $stocks->map(function (ItemStock $stock) {
            $daysUntilExpiry = (int) now()->startOfDay()->diffInDays($stock->expiry_date, false);

            return [
                'id' => $stock->id,
                'item_name' => $stock->item?->item_name,
                'item_sku' => $stock->item?->item_sku,
                'quantity' => $stock->quantity,
                'expiry_date' => $stock->expiry_date->toDateString(),
                'days_until_expiry' => $daysUntilExpiry,
                'status' => $daysUntilExpiry <= 30 ? 'critical' : 'warning',
                'warehouse_name' => $stock->warehouse?->name,
                'bin_code' => $stock->bin?->code,
                'batch_id' => $stock->batch_id,
            ];
        })->values()->toArray();
    }

    /**
     * Urgent tasks from active inbounds and delivery orders.
     */
    private function getUrgentTasks(): array
    {
        $tasks = collect();

        // Active inbounds (pending / verifying)
        $inbounds = Inbound::whereIn('status', [
                Inbound::STATUS_PENDING,
                Inbound::STATUS_VERIFYING,
            ])
            ->latest()
            ->limit(10)
            ->get();

        foreach ($inbounds as $inbound) {
            $isOverdue = $inbound->expected_arrival_date && $inbound->expected_arrival_date->isPast();

            $tasks->push([
                'id' => $inbound->id,
                'type' => 'inbound',
                'reference_id' => $inbound->inbound_number,
                'priority' => $isOverdue ? 'high' : 'medium',
                'status' => $inbound->status,
                'time' => $inbound->created_at->diffForHumans(),
                'created_at' => $inbound->created_at->toIso8601String(),
            ]);
        }

        // Active delivery orders needing picking (pending / picking)
        $pickOrders = DeliveryOrder::whereIn('status', [
                DeliveryOrder::STATUS_PENDING,
                DeliveryOrder::STATUS_PICKING,
            ])
            ->latest()
            ->limit(10)
            ->get();

        foreach ($pickOrders as $order) {
            $isOverdue = $order->due_date && $order->due_date->isPast();

            $tasks->push([
                'id' => $order->id,
                'type' => 'pick',
                'reference_id' => $order->order_number,
                'priority' => $isOverdue ? 'high' : 'medium',
                'status' => $order->status,
                'time' => $order->created_at->diffForHumans(),
                'created_at' => $order->created_at->toIso8601String(),
            ]);
        }

        // Packed orders ready for dispatch
        $dispatchOrders = DeliveryOrder::where('status', DeliveryOrder::STATUS_PACKED)
            ->latest()
            ->limit(10)
            ->get();

        foreach ($dispatchOrders as $order) {
            $isOverdue = $order->due_date && $order->due_date->isPast();

            $tasks->push([
                'id' => $order->id,
                'type' => 'dispatch',
                'reference_id' => $order->order_number,
                'priority' => $isOverdue ? 'high' : 'medium',
                'status' => $order->status,
                'time' => $order->created_at->diffForHumans(),
                'created_at' => $order->created_at->toIso8601String(),
            ]);
        }

        // Sort by created_at desc, take top 10
        return $tasks->sortByDesc('created_at')->take(10)->values()->toArray();
    }
}
