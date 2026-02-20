<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ItemMaster;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ItemMasterController extends Controller
{
    /**
     * List all items.
     */
    public function index(Request $request): JsonResponse
    {
        $query = ItemMaster::with(['category', 'stocks']);

        if ($request->has('category_id')) {
            $query->byCategory($request->category_id);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('item_sku', 'like', "%{$search}%")
                    ->orWhere('item_name', 'like', "%{$search}%");
            });
        }

        $items = $query->latest()->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $items,
        ]);
    }

    /**
     * Show a single item.
     */
    public function show(int $id): JsonResponse
    {
        $item = ItemMaster::with(['category', 'createdBy', 'updatedBy'])->find($id);

        if (!$item) {
            return response()->json([
                'success' => false,
                'message' => 'Item not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $item,
        ]);
    }

    /**
     * Store a new item.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'item_sku' => 'required|string|max:255|unique:item_master,item_sku',
            'item_name' => 'required|string|max:255',
            'category_id' => 'nullable|integer|exists:categories,id',
            'weight' => 'nullable|string|max:50',
            'storage_type' => 'nullable|integer|in:1,2,3',
            'qty_per_pallet' => 'nullable|integer|min:1',
            'qty_per_carton' => 'nullable|integer|min:1',
            'dimension_width' => 'nullable|numeric|min:0.01',
            'dimension_height' => 'nullable|numeric|min:0.01',
            'dimension_depth' => 'nullable|numeric|min:0.01',
            'dimension_unit' => 'nullable|in:cm,mm,inch,m',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors(),
            ], 422);
        }

        $item = ItemMaster::create([
            'item_sku' => $request->item_sku,
            'item_name' => $request->item_name,
            'category_id' => $request->category_id,
            'weight' => $request->weight,
            'storage_type' => $request->storage_type,
            'qty_per_pallet' => $request->qty_per_pallet,
            'qty_per_carton' => $request->qty_per_carton,
            'dimension_width' => $request->dimension_width,
            'dimension_height' => $request->dimension_height,
            'dimension_depth' => $request->dimension_depth,
            'dimension_unit' => $request->dimension_unit ?? 'cm',
            'created_by' => auth('api')->id(),
            'updated_by' => auth('api')->id(),
        ]);

        $item->load(['category']);

        return response()->json([
            'success' => true,
            'message' => 'Item created successfully',
            'data' => $item,
        ], 201);
    }

    /**
     * Update an existing item.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $item = ItemMaster::find($id);

        if (!$item) {
            return response()->json([
                'success' => false,
                'message' => 'Item not found',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'sku' => 'sometimes|required|string|max:255|unique:item_master,item_sku,' . $item->id,
            'name' => 'sometimes|required|string|max:255',
            'category_id' => 'nullable|integer|exists:categories,id',
            'weight' => 'nullable|string|max:50',
            'storage_type' => 'nullable|integer|in:1,2,3',
            'qty_per_pallet' => 'nullable|integer|min:1',
            'qty_per_carton' => 'nullable|integer|min:1',
            'dimension_width' => 'nullable|numeric|min:0.01',
            'dimension_height' => 'nullable|numeric|min:0.01',
            'dimension_depth' => 'nullable|numeric|min:0.01',
            'dimension_unit' => 'nullable|in:cm,mm,inch,m',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = ['updated_by' => auth('api')->id()];

        if ($request->has('sku')) {
            $data['item_sku'] = $request->sku;
        }
        if ($request->has('name')) {
            $data['item_name'] = $request->name;
        }
        if ($request->has('category_id')) {
            $data['category_id'] = $request->category_id;
        }
        if ($request->has('weight')) {
            $data['weight'] = $request->weight;
        }
        if ($request->has('storage_type')) {
            $data['storage_type'] = $request->storage_type;
        }
        if ($request->has('qty_per_pallet')) {
            $data['qty_per_pallet'] = $request->qty_per_pallet;
        }
        if ($request->has('qty_per_carton')) {
            $data['qty_per_carton'] = $request->qty_per_carton;
        }
        if ($request->has('dimension_width')) {
            $data['dimension_width'] = $request->dimension_width;
        }
        if ($request->has('dimension_height')) {
            $data['dimension_height'] = $request->dimension_height;
        }
        if ($request->has('dimension_depth')) {
            $data['dimension_depth'] = $request->dimension_depth;
        }
        if ($request->has('dimension_unit')) {
            $data['dimension_unit'] = $request->dimension_unit;
        }

        $item->update($data);
        $item->load(['category']);

        return response()->json([
            'success' => true,
            'message' => 'Item updated successfully',
            'data' => $item,
        ]);
    }

    /**
     * Delete an item.
     */
    public function destroy(int $id): JsonResponse
    {
        $item = ItemMaster::find($id);

        if (!$item) {
            return response()->json([
                'success' => false,
                'message' => 'Item not found',
            ], 404);
        }

        $item->delete();

        return response()->json([
            'success' => true,
            'message' => 'Item deleted successfully',
        ]);
    }
}
