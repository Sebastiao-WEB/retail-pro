<?php

namespace App\Livewire\Admin;

use App\Models\Register;
use App\Models\StockLocation;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Livewire\Component;
use Livewire\WithPagination;

class UsersPage extends Component
{
    use WithPagination;

    public string $search = '';
    public bool $modalOpen = false;
    public bool $confirmDisableOpen = false;
    public ?string $editingId = null;
    public ?string $disableId = null;

    public string $name = '';
    public string $username = '';
    public string $email = '';
    public string $password = '';
    public string $role = 'MANAGER';
    public bool $is_active = true;
    public ?string $register_id = null;
    public ?string $source_location_id = null;

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function openCreateModal(): void
    {
        abort_unless(auth()->user()?->can('users.manage'), 403);
        $this->resetForm();
        $this->modalOpen = true;
    }

    public function openEditModal(string $id): void
    {
        abort_unless(auth()->user()?->can('users.manage'), 403);
        $user = User::query()->findOrFail($id);
        $this->editingId = $user->id;
        $this->name = $user->name;
        $this->username = (string) $user->username;
        $this->email = (string) $user->email;
        $this->password = '';
        $this->role = (string) ($user->role ?: ($user->getRoleNames()->first() ?? 'MANAGER'));
        $this->is_active = (bool) $user->is_active;
        $this->register_id = $user->register_id;
        $this->source_location_id = $user->source_location_id;
        $this->modalOpen = true;
    }

    public function save(): void
    {
        abort_unless(auth()->user()?->can('users.manage'), 403);

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:255', 'unique:users,username,'.($this->editingId ?? 'NULL').',id'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email,'.($this->editingId ?? 'NULL').',id'],
            'role' => ['required', 'in:ADMIN,MANAGER,CASHIER'],
            'is_active' => ['boolean'],
            'register_id' => ['nullable', 'uuid', 'exists:registers,id'],
            'source_location_id' => ['nullable', 'uuid', 'exists:stock_locations,id'],
        ];

        if ($this->editingId) {
            $rules['password'] = ['nullable', 'string', 'min:6'];
        } else {
            $rules['password'] = ['required', 'string', 'min:6'];
        }

        $dados = $this->validate($rules);

        $payload = [
            'name' => $dados['name'],
            'username' => $dados['username'],
            'email' => $dados['email'],
            'role' => $dados['role'],
            'is_active' => $dados['is_active'],
            'register_id' => $dados['register_id'] ?: null,
            'source_location_id' => $dados['source_location_id'] ?: null,
            'caixa_atribuido' => null,
        ];

        if (! empty($dados['password'])) {
            $payload['password'] = Hash::make($dados['password']);
        }

        /** @var User $user */
        $user = User::query()->updateOrCreate(
            ['id' => $this->editingId],
            $payload
        );
        $user->syncRoles([$dados['role']]);

        session()->flash('toast', ['type' => 'success', 'message' => $this->editingId ? 'Utilizador atualizado.' : 'Utilizador criado.']);
        $this->closeModal();
    }

    public function confirmDisable(string $id): void
    {
        abort_unless(auth()->user()?->can('users.manage'), 403);
        $this->disableId = $id;
        $this->confirmDisableOpen = true;
    }

    public function disable(): void
    {
        abort_unless(auth()->user()?->can('users.manage'), 403);
        if ($this->disableId) {
            User::query()->where('id', $this->disableId)->update(['is_active' => false]);
        }
        $this->confirmDisableOpen = false;
        $this->disableId = null;
        session()->flash('toast', ['type' => 'success', 'message' => 'Utilizador desativado.']);
    }

    public function closeModal(): void
    {
        $this->modalOpen = false;
        $this->resetForm();
    }

    private function resetForm(): void
    {
        $this->editingId = null;
        $this->name = '';
        $this->username = '';
        $this->email = '';
        $this->password = '';
        $this->role = 'MANAGER';
        $this->is_active = true;
        $this->register_id = null;
        $this->source_location_id = null;
    }

    public function render()
    {
        $users = User::query()
            ->when($this->search !== '', function ($q) {
                $q->where(function ($inner) {
                    $inner->where('name', 'like', "%{$this->search}%")
                        ->orWhere('username', 'like', "%{$this->search}%")
                        ->orWhere('email', 'like', "%{$this->search}%");
                });
            })
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(10);

        return view('livewire.admin.users-page')
            ->layout('components.layouts.desktop', ['title' => 'Utilizadores e Gerentes | RetailPro'])
            ->with([
                'users' => $users,
                'registers' => Register::query()->where('is_active', true)->orderBy('name')->get(['id', 'code', 'name']),
                'locations' => StockLocation::query()->where('is_active', true)->orderBy('name')->get(['id', 'code', 'name']),
            ]);
    }
}
