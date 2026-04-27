<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\StockMovement;
use Illuminate\Http\Request;

class StockMovementController extends Controller
{
    public function index(Request $request)
    {
        $query = StockMovement::query()->with(['product']);

        if ($request->filled('product_id')) {
            $query->where('product_id', $request->string('product_id')->toString());
        }
        if ($request->filled('location_id')) {
            $locationId = $request->string('location_id')->toString();
            $query->where(function ($q) use ($locationId) {
                $q->where('from_location_id', $locationId)
                    ->orWhere('to_location_id', $locationId);
            });
        }
        if ($request->filled('type')) {
            $query->where('type', $request->string('type')->toString());
        }

        $movements = $query->latest()->limit(300)->get()->map(fn (StockMovement $item) => [
            'id' => $item->id,
            'productId' => $item->product_id,
            'productName' => $item->product?->nome,
            'fromLocationId' => $item->from_location_id,
            'toLocationId' => $item->to_location_id,
            'type' => $item->type,
            'quantity' => (float) $item->quantity,
            'unitCost' => $item->unit_cost !== null ? (float) $item->unit_cost : null,
            'referenceType' => $item->reference_type,
            'referenceId' => $item->reference_id,
            'note' => $item->note,
            'performedBy' => $item->performed_by,
            'createdAt' => optional($item->created_at)->toISOString(),
        ]);

        return response()->json(['data' => $movements]);
    }
}
