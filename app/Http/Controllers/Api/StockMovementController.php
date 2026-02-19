<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class StockMovementController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'month' => 'required|date_format:Y-m',
            'type'  => 'nullable|in:inbound,outbound',
        ]);

        $month   = $request->input('month');
        $type    = $request->input('type');
        $perPage = (int) $request->get('per_page', 15);
        $page    = (int) $request->get('page', 1);

        $results = DB::select('CALL sp_stock_movement(?, ?)', [$month, $type]);

        $total = count($results);
        $items = array_slice($results, ($page - 1) * $perPage, $perPage);

        $paginator = new LengthAwarePaginator($items, $total, $perPage, $page, [
            'path' => $request->url(),
        ]);
        $paginator->appends($request->query());

        return response()->json([
            'success' => true,
            'data'    => $paginator,
        ]);
    }
}
