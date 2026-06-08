<?php

namespace App\Http\Controllers\Admin\Web;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Admin\Web\Concerns\AuthorizesAdminWeb;
use App\Http\Controllers\Admin\Web\Concerns\RespondsAsJson;
use App\Models\Register;
use App\Models\StockLocation;
use App\Services\StockByLocationService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class StockLocationWebController extends Controller
{
    use AuthorizesAdminWeb;
    use RespondsAsJson;

    public function index(Request $request)
    {
        $this->authorizeAdmin('stock_locations.view');

        $search = $request->string('search')->toString();

        $locations = StockLocation::query()
            ->with('register')
            ->withSum(['balances as total_quantity' => fn ($q) => $q->where('quantity', '>', 0)], 'quantity')
            ->withCount(['balances as products_count' => fn ($q) => $q->where('quantity', '>', 0)])
            ->when($search !== '', function ($q) use ($search) {
                $q->where(fn ($inner) => $inner
                    ->where('code', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%")
                    ->orWhere('type', 'like', "%{$search}%"));
            })
            ->orderBy('code')
            ->paginate(10)
            ->withQueryString();

        return view('admin.stock-locations.index', [
            'locations' => $locations,
            'search' => $search,
            'canManage' => auth()->user()?->can('stock_locations.manage') ?? false,
            'registers' => Register::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'code']),
        ]);
    }

    public function show(StockLocation $stockLocation)
    {
        $this->authorizeAdmin('stock_locations.manage');

        return $this->jsonOk($this->serializeLocation($stockLocation));
    }

    public function stock(StockLocation $stockLocation, StockByLocationService $stockService)
    {
        $this->authorizeAdmin('stock_locations.view');

        $resumo = $stockService->resumoPorLocalizacao($stockLocation->id);
        $stockDetalhe = $resumo[0] ?? null;

        return $this->jsonOk($stockDetalhe);
    }

    public function store(Request $request)
    {
        $this->authorizeAdmin('stock_locations.manage');

        try {
            $payload = $this->validatedPayload($request);
            $location = StockLocation::query()->create($payload);
        } catch (ValidationException $exception) {
            return $this->jsonFromValidation($exception);
        }

        return $this->jsonOk($this->serializeLocation($location), __('toasts.location_created'), 201);
    }

    public function update(Request $request, StockLocation $stockLocation)
    {
        $this->authorizeAdmin('stock_locations.manage');

        try {
            $payload = $this->validatedPayload($request, $stockLocation->id);
            $stockLocation->update($payload);
        } catch (ValidationException $exception) {
            return $this->jsonFromValidation($exception);
        }

        return $this->jsonOk($this->serializeLocation($stockLocation->fresh()), __('toasts.location_updated'));
    }

    public function destroy(StockLocation $stockLocation)
    {
        $this->authorizeAdmin('stock_locations.manage');

        $stockLocation->update(['is_active' => false]);

        return $this->jsonOk(null, __('toasts.location_disabled'));
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedPayload(Request $request, ?string $editingId = null): array
    {
        return $request->validate([
            'register_id' => ['nullable', 'uuid', 'exists:registers,id'],
            'code' => ['required', 'string', 'max:255', 'unique:stock_locations,code,'.($editingId ?? 'NULL').',id'],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:STORE_FLOOR,WAREHOUSE,DAMAGE,RETURN_AREA,TRANSIT'],
            'is_saleable' => ['boolean'],
            'is_active' => ['boolean'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeLocation(StockLocation $location): array
    {
        return [
            'id' => $location->id,
            'register_id' => $location->register_id,
            'code' => $location->code,
            'name' => $location->name,
            'type' => $location->type,
            'is_saleable' => (bool) $location->is_saleable,
            'is_active' => (bool) $location->is_active,
        ];
    }
}
