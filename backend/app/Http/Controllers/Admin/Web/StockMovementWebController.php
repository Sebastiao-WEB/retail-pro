<?php

namespace App\Http\Controllers\Admin\Web;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Admin\Web\Concerns\AuthorizesAdminWeb;
use App\Models\StockLocation;
use App\Models\StockMovement;
use Illuminate\Http\Request;

class StockMovementWebController extends Controller
{
    use AuthorizesAdminWeb;

    public function index(Request $request)
    {
        $this->authorizeAdmin('stock.movements.view');

        $search = $request->string('search')->toString();
        $typeFilter = $request->string('typeFilter')->toString();
        $locationFilter = $request->string('locationFilter')->toString();
        $reloadsOnly = $request->boolean('reloadsOnly');

        $movements = StockMovement::query()
            ->with(['product', 'fromLocation', 'toLocation', 'performedBy', 'reloadRecord'])
            ->when($reloadsOnly, fn ($q) => $q->stockReloads())
            ->when($search !== '', function ($q) use ($search) {
                $q->whereHas('product', function ($productQuery) use ($search) {
                    $productQuery
                        ->where('nome', 'like', "%{$search}%")
                        ->orWhere('codigo_barras', 'like', "%{$search}%");
                });
            })
            ->when($typeFilter !== '', fn ($q) => $q->where('type', $typeFilter))
            ->when($locationFilter !== '', function ($q) use ($locationFilter) {
                $q->where(function ($inner) use ($locationFilter) {
                    $inner->where('from_location_id', $locationFilter)
                        ->orWhere('to_location_id', $locationFilter);
                });
            })
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return view('admin.stock-movements.index', [
            'movements' => $movements,
            'locations' => StockLocation::query()->where('is_active', true)->orderBy('name')->get(['id', 'code', 'name']),
            'search' => $search,
            'typeFilter' => $typeFilter,
            'locationFilter' => $locationFilter,
            'reloadsOnly' => $reloadsOnly,
        ]);
    }
}
