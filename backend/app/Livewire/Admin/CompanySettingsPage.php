<?php

namespace App\Livewire\Admin;

use App\Models\CompanyProfile;
use Livewire\Component;

class CompanySettingsPage extends Component
{
    public string $nomeEmpresa = '';
    public string $nif = '';
    public string $email = '';
    public string $telefone = '';
    public string $endereco = '';
    public string $banco = '';
    public string $iban = '';
    public string $rodapeFacturas = '';

    public function mount(): void
    {
        $this->carregarPerfil();
    }

    public function salvar(): void
    {
        abort_unless(auth()->user()?->can('settings.manage'), 403);

        $dados = $this->validate([
            'nomeEmpresa' => ['required', 'string', 'max:255'],
            'nif' => ['nullable', 'string', 'max:32'],
            'email' => ['nullable', 'string', 'max:255'],
            'telefone' => ['nullable', 'string', 'max:64'],
            'endereco' => ['nullable', 'string', 'max:255'],
            'banco' => ['nullable', 'string', 'max:255'],
            'iban' => ['nullable', 'string', 'max:255'],
            'rodapeFacturas' => ['nullable', 'string', 'max:2000'],
        ]);

        $perfil = CompanyProfile::query()->firstOrCreate([]);
        $perfil->fill([
            'name' => $dados['nomeEmpresa'],
            'nif' => $dados['nif'] ?: null,
            'email' => $dados['email'] ?: null,
            'phone' => $dados['telefone'] ?: null,
            'address' => $dados['endereco'] ?: null,
            'bank' => $dados['banco'] ?: null,
            'iban' => $dados['iban'] ?: null,
            'invoice_footer' => $dados['rodapeFacturas'] ?: null,
        ])->save();

        session()->flash('toast', ['type' => 'success', 'message' => __('toasts.company_settings_saved')]);
        $this->carregarPerfil();
    }

    private function carregarPerfil(): void
    {
        $perfil = CompanyProfile::query()->firstOrCreate([], [
            'name' => 'Empresa Demo Lda',
            'nif' => '400000099',
            'email' => 'geral@empresa.co.mz',
            'phone' => '+258 21 000 000',
            'address' => 'Av. 25 de Setembro, 420, Maputo, Moçambique',
            'bank' => 'BCI — Banco Comercial e de Investimentos',
            'iban' => 'MZ59 0000 0000 1234 5678 901',
            'invoice_footer' => 'Obrigado pela sua preferência. Para reclamações contacte: geral@empresa.co.mz',
        ]);

        $this->nomeEmpresa = (string) ($perfil->name ?? '');
        $this->nif = (string) ($perfil->nif ?? '');
        $this->email = (string) ($perfil->email ?? '');
        $this->telefone = (string) ($perfil->phone ?? '');
        $this->endereco = (string) ($perfil->address ?? '');
        $this->banco = (string) ($perfil->bank ?? '');
        $this->iban = (string) ($perfil->iban ?? '');
        $this->rodapeFacturas = (string) ($perfil->invoice_footer ?? '');
    }

    public function render()
    {
        abort_unless(auth()->user()?->can('settings.view'), 403);

        return view('livewire.admin.company-settings-page')
            ->layout('components.layouts.desktop', ['title' => __('pages.titles.settings')]);
    }
}

