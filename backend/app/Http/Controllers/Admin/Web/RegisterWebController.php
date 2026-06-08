<?php

namespace App\Http\Controllers\Admin\Web;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Admin\Web\Concerns\AuthorizesAdminWeb;
use App\Http\Controllers\Admin\Web\Concerns\RespondsAsJson;
use App\Models\Register;
use App\Models\StockLocation;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class RegisterWebController extends Controller
{
    use AuthorizesAdminWeb;
    use RespondsAsJson;

    public function index(Request $request)
    {
        $this->authorizeAdmin('registers.view');

        $search = $request->string('search')->toString();

        $registers = Register::query()
            ->with('stockLocations')
            ->when($search !== '', function ($q) use ($search) {
                $q->where(fn ($inner) => $inner
                    ->where('code', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%"));
            })
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return view('admin.registers.index', [
            'registers' => $registers,
            'search' => $search,
            'canManage' => auth()->user()?->can('registers.manage') ?? false,
        ]);
    }

    public function edit(Request $request, Register $register)
    {
        $this->authorizeAdmin('registers.manage');

        $register->load('stockLocations');
        $search = $request->string('search')->toString();
        $assignedLocationIds = $register->stockLocations->pluck('id');

        $locations = StockLocation::query()
            ->where(function ($query) use ($assignedLocationIds) {
                $query->where('is_active', true);

                if ($assignedLocationIds->isNotEmpty()) {
                    $query->orWhereIn('id', $assignedLocationIds);
                }
            })
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'type', 'is_active']);

        return view('admin.registers.edit', [
            'register' => $register,
            'locations' => $locations,
            'search' => $search,
            'backUrl' => route('registers.index', array_filter([
                'search' => $search !== '' ? $search : null,
            ])),
        ]);
    }

    public function show(Register $register)
    {
        $this->authorizeAdmin('registers.manage');

        return $this->jsonOk($this->serializeRegister($register));
    }

    public function store(Request $request)
    {
        $this->authorizeAdmin('registers.manage');

        try {
            $payload = $this->validatedPayload($request);
            $register = Register::query()->create($payload);
        } catch (ValidationException $exception) {
            return $this->jsonFromValidation($exception);
        }

        return $this->jsonOk($this->serializeRegister($register), __('toasts.register_created'), 201);
    }

    public function update(Request $request, Register $register)
    {
        $this->authorizeAdmin('registers.manage');

        try {
            [$payload, $locationIds] = $this->validatedPayload($request, $register->id, true);
            $register->update($payload);
            $register->stockLocations()->sync($locationIds);
        } catch (ValidationException $exception) {
            if ($request->expectsJson()) {
                return $this->jsonFromValidation($exception);
            }

            throw $exception;
        }

        if ($request->expectsJson()) {
            return $this->jsonOk($this->serializeRegister($register->fresh()), __('toasts.register_updated'));
        }

        return redirect()
            ->route('registers.index', array_filter([
                'search' => $request->string('return_search')->toString() ?: null,
            ]))
            ->with('toast', ['type' => 'success', 'message' => __('toasts.register_updated')]);
    }

    public function destroy(Register $register)
    {
        $this->authorizeAdmin('registers.manage');

        $register->update(['is_active' => false]);

        return $this->jsonOk(null, __('toasts.register_disabled'));
    }

    /**
     * @return ($includeStockLocations is true ? array{0: array<string, mixed>, 1: list<string>} : array<string, mixed>)
     */
    private function validatedPayload(Request $request, ?string $editingId = null, bool $includeStockLocations = false): array
    {
        $rules = [
            'code' => ['required', 'string', 'max:255', 'unique:registers,code,'.($editingId ?? 'NULL').',id'],
            'name' => ['required', 'string', 'max:255'],
            'is_active' => ['boolean'],
        ];

        if ($includeStockLocations) {
            $rules['stock_location_ids'] = ['nullable', 'array'];
            $rules['stock_location_ids.*'] = ['uuid', 'exists:stock_locations,id'];
        }

        $data = $request->validate($rules);

        if (! $includeStockLocations) {
            return $data;
        }

        $locationIds = collect($data['stock_location_ids'] ?? [])
            ->filter()
            ->unique()
            ->values()
            ->all();

        unset($data['stock_location_ids']);

        return [$data, $locationIds];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeRegister(Register $register): array
    {
        return [
            'id' => $register->id,
            'code' => $register->code,
            'name' => $register->name,
            'is_active' => (bool) $register->is_active,
        ];
    }
}
