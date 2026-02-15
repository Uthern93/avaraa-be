<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Bin;
use App\Models\ItemStock;
use App\Models\Rack;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RackController extends Controller
{
    /**
     * Fetch racks filtered by warehouse.
     *
     * GET /racks?warehouse_id=1
     */
    public function index(Request $request): JsonResponse
    {
        $query = Rack::select('id', 'warehouse_id', 'code', 'label');

        if ($request->has('warehouse_id')) {
            $query->inWarehouse($request->warehouse_id);
        }

        $racks = $query->orderBy('code')->get();

        return response()->json([
            'success' => true,
            'data' => $racks,
        ]);
    }

    /**
     * Get bins for a specific rack with occupancy info.
     *
     * GET /racks/{rackId}/bins
     */
    public function bins(int $rackId): JsonResponse
    {
        $rack = Rack::find($rackId);

        if (!$rack) {
            return response()->json([
                'success' => false,
                'message' => 'Rack not found',
            ], 404);
        }

        $bins = Bin::where('rack_id', $rackId)
            ->orderBy('number')
            ->get();

        $data = $bins->map(function (Bin $bin) {
            $isOccupied = !$bin->is_available;

            $result = [
                'id' => $bin->id,
                'rack_id' => $bin->rack_id,
                'code' => str_pad($bin->number, 2, '0', STR_PAD_LEFT),
                'label' => $bin->code,
                'is_occupied' => $isOccupied,
            ];

            if ($isOccupied) {
                $currentStock = ItemStock::where('bin_id', $bin->id)
                    ->where('quantity', '>', 0)
                    ->with('item:id,item_sku,item_name')
                    ->first();

                if ($currentStock) {
                    $result['current_item'] = [
                        'id' => $currentStock->item->id,
                        'item_sku' => $currentStock->item->item_sku,
                        'item_name' => $currentStock->item->item_name,
                    ];
                }
            }

            return $result;
        });

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }
}
