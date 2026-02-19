<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DeliveryOrder;
use App\Models\DeliveryOrderItem;
use App\Models\Bin;
use App\Models\Dispatch;
use App\Models\ItemStock;
use App\Models\StockMovement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class DispatchRequestController extends Controller
{
    /**
     * List all dispatch requests (delivery orders).
     */
    public function index(Request $request): JsonResponse
    {
        $query = DeliveryOrder::with(['user', 'items.item.stocks', 'items.warehouse', 'items.bin.rack', 'dispatch.createdBy']);

        if ($request->filled('status')) {
            $query->byStatus($request->status);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('order_number', 'like', "%{$search}%")
                    ->orWhereHas('user', fn($u) => $u->where('name', 'like', "%{$search}%"));
            });
        }

        $currentUserId = auth('api')->id();
        $orders = $query->latest()->paginate($request->get('per_page', 15));

        $orders->getCollection()->transform(function ($order) use ($currentUserId) {
            $order->is_current_user = $order->user_id === $currentUserId;
            return $order;
        });

        return response()->json([
            'success' => true,
            'data' => $orders,
        ]);
    }

    /**
     * Show a single dispatch request with items.
     */
    public function show(int $id): JsonResponse
    {
        $order = DeliveryOrder::with(['user', 'items.item.stocks', 'items.warehouse', 'items.bin', 'dispatch.createdBy', 'createdBy', 'updatedBy'])
            ->find($id);

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Dispatch request not found',
            ], 404);
        }

        $order->is_current_user = $order->user_id === auth('api')->id();

        return response()->json([
            'success' => true,
            'data' => $order,
        ]);
    }

    /**
     * Submit a new dispatch request.
     *
     * Creates a delivery_order (status: picking) and delivery_order_items.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'due_date' => 'nullable|date|after_or_equal:today',
            'notes' => 'nullable|string|max:1000',
            'items' => 'required|array|min:1',
            'items.*.item_id' => 'required|integer|exists:item_master,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.warehouse_id' => 'required|integer|exists:warehouses,id',
            'items.*.batch_id' => 'required|string|exists:inbounds,batch_id',
            'items.*.bin_id' => 'required|integer|exists:bins,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Validate stock availability before creating order
        // Aggregate quantities per stock key to handle duplicate bin entries
        $stockDemand = [];
        foreach ($request->items as $index => $itemData) {
            $key = "{$itemData['item_id']}_{$itemData['warehouse_id']}_{$itemData['batch_id']}_{$itemData['bin_id']}";
            if (!isset($stockDemand[$key])) {
                $stockDemand[$key] = [
                    'item_id' => $itemData['item_id'],
                    'warehouse_id' => $itemData['warehouse_id'],
                    'batch_id' => $itemData['batch_id'],
                    'bin_id' => $itemData['bin_id'],
                    'total_quantity' => 0,
                ];
            }
            $stockDemand[$key]['total_quantity'] += $itemData['quantity'];
        }

        foreach ($stockDemand as $demand) {
            $stock = ItemStock::where('item_id', $demand['item_id'])
                ->where('warehouse_id', $demand['warehouse_id'])
                ->where('batch_id', $demand['batch_id'])
                ->where('bin_id', $demand['bin_id'])
                ->first();

            if (!$stock) {
                return response()->json([
                    'success' => false,
                    'message' => "No stock found for item {$demand['item_id']} in warehouse {$demand['warehouse_id']}, batch {$demand['batch_id']}, bin {$demand['bin_id']}",
                ], 422);
            }

            if ($stock->quantity < $demand['total_quantity']) {
                return response()->json([
                    'success' => false,
                    'message' => "Insufficient stock for item {$demand['item_id']} in bin {$demand['bin_id']}. Available: {$stock->quantity}, Requested: {$demand['total_quantity']}",
                ], 422);
            }
        }

        $user = auth('api')->user();
        $prefix = $user->isCustomer() ? 'ORD' : 'REQ';

        $order = DB::transaction(function () use ($request, $user, $prefix) {
            $order = DeliveryOrder::create([
                'order_number' => DeliveryOrder::generateOrderNumber($prefix),
                'user_id' => $user->id,
                'due_date' => $request->due_date,
                'status' => DeliveryOrder::STATUS_PENDING,
                'notes' => $request->notes,
                'created_by' => auth('api')->id(),
                'updated_by' => auth('api')->id(),
            ]);

            foreach ($request->items as $itemData) {
                // Get stock record for expiry_date
                $stock = ItemStock::where('item_id', $itemData['item_id'])
                    ->where('warehouse_id', $itemData['warehouse_id'])
                    ->where('batch_id', $itemData['batch_id'])
                    ->where('bin_id', $itemData['bin_id'])
                    ->first();

                $orderItem = DeliveryOrderItem::create([
                    'delivery_order_id' => $order->id,
                    'item_id' => $itemData['item_id'],
                    'warehouse_id' => $itemData['warehouse_id'],
                    'batch_id' => $itemData['batch_id'],
                    'bin_id' => $itemData['bin_id'],
                    'expiry_date' => $stock->expiry_date,
                    'quantity' => $itemData['quantity'],
                    'created_by' => auth('api')->id(),
                ]);

                // Deduct stock
                $stock->decreaseQuantity($itemData['quantity']);

                // Free bin if stock is fully depleted
                if ($stock->fresh()->quantity <= 0) {
                    Bin::where('id', $itemData['bin_id'])
                        ->update(['is_available' => true]);
                }

                // Create stock movement (OUT)
                StockMovement::createOutMovement([
                    'item_id' => $itemData['item_id'],
                    'warehouse_id' => $itemData['warehouse_id'],
                    'bin_id' => $itemData['bin_id'],
                    'batch_id' => $itemData['batch_id'],
                    'quantity' => $itemData['quantity'],
                    'reference_type' => StockMovement::REF_DELIVERY_ORDER_ITEM,
                    'reference_id' => $orderItem->id,
                ]);
            }

            return $order;
        });

        $order->load(['user', 'items.item.stocks']);

        return response()->json([
            'success' => true,
            'message' => 'Dispatch request submitted successfully',
            'data' => $order,
        ], 201);
    }

    /**
     * Start picking an order.
     *
     * PUT /dispatch-requests/{id}/start-picking
     */
    public function startPicking(int $id): JsonResponse
    {
        $order = DeliveryOrder::find($id);

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Dispatch request not found',
            ], 404);
        }

        if ($order->status !== DeliveryOrder::STATUS_PENDING) {
            return response()->json([
                'success' => false,
                'message' => 'Only pending orders can start picking. Current status: ' . $order->status,
            ], 422);
        }

        $order->update([
            'status' => DeliveryOrder::STATUS_PICKING,
            'updated_by' => auth('api')->id(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Picking started',
            'data' => [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'status' => $order->status,
            ],
        ]);
    }

    /**
     * Complete picking an order.
     *
     * PUT /dispatch-requests/{id}/complete-picking
     */
    public function completePicking(int $id): JsonResponse
    {
        $order = DeliveryOrder::find($id);

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Dispatch request not found',
            ], 404);
        }

        if ($order->status !== DeliveryOrder::STATUS_PICKING) {
            return response()->json([
                'success' => false,
                'message' => 'Only orders being picked can complete picking. Current status: ' . $order->status,
            ], 422);
        }

        $order->update([
            'status' => DeliveryOrder::STATUS_PICKED,
            'updated_by' => auth('api')->id(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Picking completed',
            'data' => [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'status' => $order->status,
            ],
        ]);
    }

    /**
     * Start packing an order.
     *
     * PUT /dispatch-requests/{id}/start-packing
     */
    public function startPacking(int $id): JsonResponse
    {
        $order = DeliveryOrder::find($id);

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Dispatch request not found',
            ], 404);
        }

        if ($order->status !== DeliveryOrder::STATUS_PICKED) {
            return response()->json([
                'success' => false,
                'message' => 'Only picked orders can start packing. Current status: ' . $order->status,
            ], 422);
        }

        $order->update([
            'status' => DeliveryOrder::STATUS_PACKING,
            'updated_by' => auth('api')->id(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Packing started',
            'data' => [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'status' => $order->status,
            ],
        ]);
    }

    /**
     * Complete packing an order.
     *
     * PUT /dispatch-requests/{id}/complete-packing
     */
    public function completePacking(int $id): JsonResponse
    {
        $order = DeliveryOrder::find($id);

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Dispatch request not found',
            ], 404);
        }

        if ($order->status !== DeliveryOrder::STATUS_PACKING) {
            return response()->json([
                'success' => false,
                'message' => 'Only orders being packed can complete packing. Current status: ' . $order->status,
            ], 422);
        }

        $order->update([
            'status' => DeliveryOrder::STATUS_PACKED,
            'updated_by' => auth('api')->id(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Packing completed',
            'data' => [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'status' => $order->status,
            ],
        ]);
    }

    /**
     * Dispatch a packed order.
     *
     * PUT /dispatch-requests/{id}/dispatch
     */
    public function dispatch(Request $request, int $id): JsonResponse
    {
        $order = DeliveryOrder::find($id);

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Dispatch request not found',
            ], 404);
        }

        if ($order->status !== DeliveryOrder::STATUS_PACKED) {
            return response()->json([
                'success' => false,
                'message' => 'Only packed orders can be dispatched. Current status: ' . $order->status,
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'driver_name' => 'required|string|max:255',
            'vehicle_no' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors(),
            ], 422);
        }

        $dispatch = DB::transaction(function () use ($request, $order) {
            $order->update([
                'status' => DeliveryOrder::STATUS_DISPATCHED,
                'updated_by' => auth('api')->id(),
            ]);

            return Dispatch::create([
                'delivery_order_id' => $order->id,
                'driver_name' => $request->driver_name,
                'vehicle_name' => $request->vehicle_no,
                'dispatch_date' => now(),
                'created_by' => auth('api')->id(),
            ]);
        });

        $dispatch->load(['deliveryOrder.user', 'deliveryOrder.items.item', 'createdBy']);

        return response()->json([
            'success' => true,
            'message' => 'Order dispatched successfully',
            'data' => $dispatch,
        ]);
    }
}
