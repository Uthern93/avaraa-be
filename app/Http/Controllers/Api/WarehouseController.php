<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ItemStock;
use App\Models\Warehouse;
use Illuminate\Http\JsonResponse;

class WarehouseController extends Controller
{
    /**
     * Fetch all warehouses (for dropdowns).
     */
    public function index(): JsonResponse
    {
        $warehouses = Warehouse::select('id', 'name', 'location')
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $warehouses,
        ]);
    }

    /**
     * Get warehouse layout with all racks and bins including occupancy.
     *
     * GET /warehouses/{id}/layout
     */
    public function layout(int $id): JsonResponse
    {
        $warehouse = Warehouse::find($id);

        if (!$warehouse) {
            return response()->json([
                'success' => false,
                'message' => 'Warehouse not found',
            ], 404);
        }

        $racks = $warehouse->racks()
            ->with(['bins' => fn($q) => $q->orderBy('number')])
            ->orderBy('code')
            ->get();

        // Preload all occupied bin stocks in one query
        $occupiedBinIds = $racks->flatMap(fn($rack) => $rack->bins->where('is_available', false)->pluck('id'));

        $stocksByBin = collect();
        if ($occupiedBinIds->isNotEmpty()) {
            $stocksByBin = ItemStock::whereIn('bin_id', $occupiedBinIds)
                ->where('quantity', '>', 0)
                ->with('item:id,item_sku,item_name')
                ->get()
                ->groupBy('bin_id');
        }

        $data = $racks->map(function ($rack) use ($stocksByBin) {
            $bins = $rack->bins->map(function ($bin) use ($stocksByBin) {
                $isOccupied = !$bin->is_available;

                $result = [
                    'id' => $bin->id,
                    'rack_id' => $bin->rack_id,
                    'number' => str_pad($bin->number, 2, '0', STR_PAD_LEFT),
                    'code' => $bin->code,
                    'is_occupied' => $isOccupied,
                ];

                if ($isOccupied && $stocksByBin->has($bin->id)) {
                    $stock = $stocksByBin[$bin->id]->first();
                    if ($stock && $stock->item) {
                        $result['current_item'] = [
                            'id' => $stock->item->id,
                            'item_sku' => $stock->item->item_sku,
                            'item_name' => $stock->item->item_name,
                            'quantity' => $stock->quantity,
                            'batch_id' => $stock->batch_id,
                            'expiry_date' => $stock->expiry_date?->toDateString(),
                        ];
                    }
                }

                return $result;
            });

            return [
                'id' => $rack->id,
                'code' => $rack->code,
                'label' => $rack->label,
                'total_bins' => $bins->count(),
                'occupied_bins' => $bins->where('is_occupied', true)->count(),
                'available_bins' => $bins->where('is_occupied', false)->count(),
                'bins' => $bins->values(),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $warehouse->id,
                'name' => $warehouse->name,
                'location' => $warehouse->location,
                'total_racks' => $data->count(),
                'total_bins' => $data->sum('total_bins'),
                'total_occupied' => $data->sum('occupied_bins'),
                'total_available' => $data->sum('available_bins'),
                'racks' => $data,
            ],
        ]);
    }
}
