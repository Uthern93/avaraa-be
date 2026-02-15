<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Bin;
use App\Models\Inbound;
use App\Models\InboundItem;
use App\Models\ItemStock;
use App\Models\StockMovement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class InboundController extends Controller
{
    /**
     * List all inbound applications (paginated).
     *
     * GET /inbound-applications?search=&status=&warehouse_id=&page=1&per_page=10
     */
    public function index(Request $request): JsonResponse
    {
        $query = Inbound::with(['warehouse', 'items.item', 'items.rack', 'items.bin', 'createdBy']);

        if ($request->has('status')) {
            $query->byStatus($request->status);
        }

        if ($request->has('warehouse_id')) {
            $query->where('warehouse_id', $request->warehouse_id);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('inbound_number', 'like', "%{$search}%")
                    ->orWhere('batch_id', 'like', "%{$search}%");
            });
        }

        $inbounds = $query->latest()->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $inbounds,
        ]);
    }

    /**
     * Show a single inbound with items.
     *
     * GET /inbound-applications/{id}
     */
    public function show(int $id): JsonResponse
    {
        $inbound = Inbound::with(['warehouse', 'items.item', 'items.rack', 'items.bin', 'createdBy', 'updatedBy'])
            ->find($id);

        if (!$inbound) {
            return response()->json([
                'success' => false,
                'message' => 'Inbound not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $inbound,
        ]);
    }

    /**
     * Create a new inbound application.
     *
     * POST /inbound-applications
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'warehouse_id' => 'required|integer|exists:warehouses,id',
            'expected_date' => 'nullable|date|after_or_equal:today',
            'notes' => 'nullable|string|max:1000',
            'items' => 'required|array|min:1',
            'items.*.item_id' => 'required|integer|exists:item_master,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.rack_id' => 'required|integer|exists:racks,id',
            'items.*.expiry_date' => 'nullable|date',
            'items.*.maintenance_date' => 'nullable|date',
            'items.*.manufacturing_year' => 'nullable|integer|min:1900|max:' . (date('Y') + 1),
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors(),
            ], 422);
        }

        $inbound = DB::transaction(function () use ($request) {
            $inbound = Inbound::create([
                'inbound_number' => Inbound::generateInboundNumber(),
                'batch_id' => Inbound::generateBatchId(),
                'warehouse_id' => $request->warehouse_id,
                'expected_arrival_date' => $request->expected_date,
                'status' => Inbound::STATUS_PENDING,
                'notes' => $request->notes,
                'created_by' => auth('api')->id(),
                'updated_by' => auth('api')->id(),
            ]);

            foreach ($request->items as $itemData) {
                InboundItem::create([
                    'inbound_id' => $inbound->id,
                    'item_id' => $itemData['item_id'],
                    'quantity' => $itemData['quantity'],
                    'rack_id' => $itemData['rack_id'],
                    'expiry_date' => $itemData['expiry_date'] ?? null,
                    'maintenance_date' => $itemData['maintenance_date'] ?? null,
                    'manufacturing_year' => $itemData['manufacturing_year'] ?? null,
                    'status' => InboundItem::STATUS_PENDING,
                    'created_by' => auth('api')->id(),
                ]);
            }

            return $inbound;
        });

        $inbound->load(['warehouse', 'items.item', 'items.rack']);

        return response()->json([
            'success' => true,
            'message' => 'Inbound application created successfully',
            'data' => $inbound,
        ], 201);
    }

    /**
     * Start verification of an inbound application.
     *
     * PUT /inbound-applications/{id}/verify
     */
    public function verify(int $id): JsonResponse
    {
        $inbound = Inbound::find($id);

        if (!$inbound) {
            return response()->json([
                'success' => false,
                'message' => 'Inbound not found',
            ], 404);
        }

        if ($inbound->status !== Inbound::STATUS_PENDING) {
            return response()->json([
                'success' => false,
                'message' => 'Only pending inbound applications can be verified. Current status: ' . $inbound->status,
            ], 422);
        }

        $inbound->update([
            'status' => Inbound::STATUS_VERIFYING,
            'updated_by' => auth('api')->id(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Verification started',
            'data' => [
                'id' => $inbound->id,
                'inbound_number' => $inbound->inbound_number,
                'status' => $inbound->status,
            ],
        ]);
    }

    /**
     * Putaway (store) a single inbound item into a bin.
     *
     * PUT /inbound-applications/{id}/items/{itemId}/putaway
     *
     * Request body: { "bin_id": 5, "received_quantity": 5 }
     */
    public function putaway(Request $request, int $id, int $itemId): JsonResponse
    {
        $inbound = Inbound::find($id);

        if (!$inbound) {
            return response()->json([
                'success' => false,
                'message' => 'Inbound not found',
            ], 404);
        }

        if ($inbound->status !== Inbound::STATUS_VERIFYING) {
            return response()->json([
                'success' => false,
                'message' => 'Inbound must be in verifying status to putaway items. Current status: ' . $inbound->status,
            ], 422);
        }

        $inboundItem = InboundItem::where('inbound_id', $id)
            ->where('id', $itemId)
            ->first();

        if (!$inboundItem) {
            return response()->json([
                'success' => false,
                'message' => 'Inbound item not found',
            ], 404);
        }

        if ($inboundItem->isStored()) {
            return response()->json([
                'success' => false,
                'message' => 'This item has already been stored',
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'rack_id' => 'required|integer|exists:racks,id',
            'bin_id' => 'required|integer|exists:bins,id',
            'received_quantity' => 'required|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Validate bin belongs to the selected rack
        $bin = Bin::find($request->bin_id);
        if ($bin->rack_id !== $request->rack_id) {
            return response()->json([
                'success' => false,
                'message' => 'The selected bin does not belong to the selected rack',
            ], 422);
        }

        try {
            DB::transaction(function () use ($inbound, $inboundItem, $bin, $request) {
                // Update inbound item (rack may have changed during verification)
                $inboundItem->update([
                    'rack_id' => $request->rack_id,
                    'bin_id' => $bin->id,
                    'received_quantity' => $request->received_quantity,
                    'status' => InboundItem::STATUS_STORED,
                ]);

                // Mark bin as occupied
                $bin->update(['is_available' => false]);

                // Create or update item stock
                $stock = ItemStock::where('item_id', $inboundItem->item_id)
                    ->where('warehouse_id', $inbound->warehouse_id)
                    ->where('bin_id', $bin->id)
                    ->where('batch_id', $inbound->batch_id)
                    ->first();

                if ($stock) {
                    $stock->increaseQuantity($request->received_quantity);
                } else {
                    ItemStock::create([
                        'item_id' => $inboundItem->item_id,
                        'warehouse_id' => $inbound->warehouse_id,
                        'bin_id' => $bin->id,
                        'batch_id' => $inbound->batch_id,
                        'expiry_date' => $inboundItem->expiry_date,
                        'maintenance_date' => $inboundItem->maintenance_date,
                        'manufacturing_year' => $inboundItem->manufacturing_year,
                        'quantity' => $request->received_quantity,
                    ]);
                }

                // Create stock movement (IN)
                StockMovement::createInMovement([
                    'item_id' => $inboundItem->item_id,
                    'warehouse_id' => $inbound->warehouse_id,
                    'bin_id' => $bin->id,
                    'batch_id' => $inbound->batch_id,
                    'quantity' => $request->received_quantity,
                    'reference_type' => StockMovement::REF_INBOUND_ITEM,
                    'reference_id' => $inboundItem->id,
                ]);

                // Auto-complete inbound if all items are stored
                $totalItems = InboundItem::where('inbound_id', $inbound->id)->count();
                $storedItems = InboundItem::where('inbound_id', $inbound->id)
                    ->where('status', InboundItem::STATUS_STORED)
                    ->count();

                if ($totalItems === $storedItems) {
                    $inbound->update([
                        'status' => Inbound::STATUS_COMPLETED,
                        'updated_by' => auth('api')->id(),
                    ]);
                }
            });
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to store item: ' . $e->getMessage(),
            ], 422);
        }

        $inboundItem->refresh();
        $inboundItem->load(['item', 'rack']);

        return response()->json([
            'success' => true,
            'message' => 'Item stored in ' . $inboundItem->bin_location,
            'data' => $inboundItem,
        ]);
    }
}
