<?php

namespace App\Http\Controllers\Admin\Web;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Admin\Web\Concerns\AuthorizesAdminWeb;
use App\Http\Controllers\Admin\Web\Concerns\RespondsAsJson;
use App\Models\Register;
use App\Models\StockLocation;
use App\Services\StockByLocationService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
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
            ->with('registers')
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

    public function edit(Request $request, StockLocation $stockLocation)
    {
        $this->authorizeAdmin('stock_locations.manage');

        $stockLocation->load('registers');
        $search = $request->string('search')->toString();

        return view('admin.stock-locations.edit', [
            'location' => $stockLocation,
            'search' => $search,
            'registers' => Register::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'code']),
            'backUrl' => route('stock-locations.index', array_filter([
                'search' => $search !== '' ? $search : null,
            ])),
        ]);
    }

    public function show(StockLocation $stockLocation)
    {
        $this->authorizeAdmin('stock_locations.manage');

        $stockLocation->load('registers');

        return $this->jsonOk($this->serializeLocation($stockLocation));
    }

    public function stock(Request $request, StockLocation $stockLocation, StockByLocationService $stockService)
    {
        $this->authorizeAdmin('stock_locations.view');

        $stockLocation->load('registers');
        $resumo = $stockService->resumoPorLocalizacao($stockLocation->id);
        $stockDetalhe = $resumo[0] ?? null;

        if ($request->expectsJson()) {
            return $this->jsonOk($stockDetalhe);
        }

        $search = $request->string('search')->toString();
        $itens = collect($stockDetalhe['itens'] ?? []);
        $page = max(1, $request->integer('page', 1));

        $itensPaginated = new LengthAwarePaginator(
            $itens->forPage($page, 20)->values()->all(),
            $itens->count(),
            20,
            $page,
            [
                'path' => $request->url(),
                'query' => $request->except('page'),
            ],
        );

        return view('admin.stock-locations.stock', [
            'location' => $stockLocation,
            'stock' => $stockDetalhe,
            'itens' => $itensPaginated,
            'backUrl' => route('stock-locations.index', array_filter([
                'search' => $search !== '' ? $search : null,
            ])),
        ]);
    }

    public function store(Request $request)
    {
        $this->authorizeAdmin('stock_locations.manage');

        try {
            [$payload, $registerIds] = $this->validatedPayload($request);
            $location = StockLocation::query()->create($payload);
            $location->registers()->sync($registerIds);
            $location->load('registers');
        } catch (ValidationException $exception) {
            return $this->jsonFromValidation($exception);
        }

        return $this->jsonOk($this->serializeLocation($location), __('toasts.location_created'), 201);
    }

    public function update(Request $request, StockLocation $stockLocation)
    {
        $this->authorizeAdmin('stock_locations.manage');

        try {
            [$payload, $registerIds] = $this->validatedPayload($request, $stockLocation->id);
            $stockLocation->update($payload);
            $stockLocation->registers()->sync($registerIds);
        } catch (ValidationException $exception) {
            if ($request->expectsJson()) {
                return $this->jsonFromValidation($exception);
            }

            throw $exception;
        }

        if ($request->expectsJson()) {
            $stockLocation->load('registers');

            return $this->jsonOk($this->serializeLocation($stockLocation), __('toasts.location_updated'));
        }

        return redirect()
            ->route('stock-locations.index', array_filter([
                'search' => $request->string('return_search')->toString() ?: null,
            ]))
            ->with('toast', ['type' => 'success', 'message' => __('toasts.location_updated')]);
    }

    public function destroy(StockLocation $stockLocation)
    {
        $this->authorizeAdmin('stock_locations.manage');

        $stockLocation->update(['is_active' => false]);

        return $this->jsonOk(null, __('toasts.location_disabled'));
    }

    /**
     * @return array{0: array<string, mixed>, 1: list<string>}
     */
    private function validatedPayload(Request $request, ?string $editingId = null): array
    {
        $data = $request->validate([
            'register_ids' => ['nullable', 'array'],
            'register_ids.*' => ['uuid', 'exists:registers,id'],
            'code' => ['required', 'string', 'max:255', 'unique:stock_locations,code,'.($editingId ?? 'NULL').',id'],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:STORE_FLOOR,WAREHOUSE,DAMAGE,RETURN_AREA,TRANSIT'],
            'is_saleable' => ['boolean'],
            'is_active' => ['boolean'],
        ]);

        $registerIds = collect($data['register_ids'] ?? [])
            ->filter()
            ->unique()
            ->values()
            ->all();

        unset($data['register_ids']);

        return [$data, $registerIds];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeLocation(StockLocation $location): array
    {
        return [
            'id' => $location->id,
            'register_ids' => $location->registers->pluck('id')->all(),
            'code' => $location->code,
            'name' => $location->name,
            'type' => $location->type,
            'is_saleable' => (bool) $location->is_saleable,
            'is_active' => (bool) $location->is_active,
        ];
    }
}
