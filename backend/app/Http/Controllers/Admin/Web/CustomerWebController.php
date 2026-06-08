<?php

namespace App\Http\Controllers\Admin\Web;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Admin\Web\Concerns\AuthorizesAdminWeb;
use App\Http\Controllers\Admin\Web\Concerns\RespondsAsJson;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CustomerWebController extends Controller
{
    use AuthorizesAdminWeb;
    use RespondsAsJson;

    public function index(Request $request)
    {
        $this->authorizeAdmin('customers.view');

        $search = $request->string('search')->toString();

        $clientes = Customer::query()
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($inner) use ($search) {
                    $inner->where('nome', 'like', "%{$search}%")
                        ->orWhere('telefone', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('nuit', 'like', "%{$search}%");
                });
            })
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return view('admin.customers.index', [
            'clientes' => $clientes,
            'search' => $search,
            'canManage' => auth()->user()?->can('customers.manage') ?? false,
        ]);
    }

    public function show(Customer $customer)
    {
        $this->authorizeAdmin('customers.manage');

        return $this->jsonOk($this->serializeCustomer($customer));
    }

    public function store(Request $request)
    {
        $this->authorizeAdmin('customers.manage');

        try {
            $payload = $this->validatedPayload($request);
            $customer = Customer::query()->create($payload);
        } catch (ValidationException $exception) {
            return $this->jsonFromValidation($exception);
        }

        return $this->jsonOk($this->serializeCustomer($customer), __('toasts.customer_created'), 201);
    }

    public function update(Request $request, Customer $customer)
    {
        $this->authorizeAdmin('customers.manage');

        try {
            $payload = $this->validatedPayload($request);
            $customer->update($payload);
        } catch (ValidationException $exception) {
            return $this->jsonFromValidation($exception);
        }

        return $this->jsonOk($this->serializeCustomer($customer->fresh()), __('toasts.customer_updated'));
    }

    public function destroy(Customer $customer)
    {
        $this->authorizeAdmin('customers.manage');

        $customer->update(['is_active' => false]);

        return $this->jsonOk(null, __('toasts.customer_disabled'));
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedPayload(Request $request): array
    {
        return $request->validate([
            'nome' => ['required', 'string', 'max:255'],
            'telefone' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'nuit' => ['nullable', 'string', 'max:255'],
            'is_active' => ['boolean'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeCustomer(Customer $customer): array
    {
        return [
            'id' => $customer->id,
            'nome' => $customer->nome,
            'telefone' => (string) ($customer->telefone ?? ''),
            'email' => (string) ($customer->email ?? ''),
            'nuit' => (string) ($customer->nuit ?? ''),
            'is_active' => (bool) $customer->is_active,
        ];
    }
}
