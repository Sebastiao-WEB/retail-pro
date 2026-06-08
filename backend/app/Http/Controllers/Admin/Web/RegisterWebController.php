<?php

namespace App\Http\Controllers\Admin\Web;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Admin\Web\Concerns\AuthorizesAdminWeb;
use App\Http\Controllers\Admin\Web\Concerns\RespondsAsJson;
use App\Models\Register;
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
            ->with('sourceLocation')
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
            $payload = $this->validatedPayload($request, $register->id);
            $register->update($payload);
        } catch (ValidationException $exception) {
            return $this->jsonFromValidation($exception);
        }

        return $this->jsonOk($this->serializeRegister($register->fresh()), __('toasts.register_updated'));
    }

    public function destroy(Register $register)
    {
        $this->authorizeAdmin('registers.manage');

        $register->update(['is_active' => false]);

        return $this->jsonOk(null, __('toasts.register_disabled'));
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedPayload(Request $request, ?string $editingId = null): array
    {
        return $request->validate([
            'code' => ['required', 'string', 'max:255', 'unique:registers,code,'.($editingId ?? 'NULL').',id'],
            'name' => ['required', 'string', 'max:255'],
            'is_active' => ['boolean'],
        ]);
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
