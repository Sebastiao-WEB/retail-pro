<?php

namespace App\Http\Controllers\Admin\Web;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Admin\Web\Concerns\AuthorizesAdminWeb;
use App\Http\Controllers\Admin\Web\Concerns\RespondsAsJson;
use App\Models\CompanyProfile;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CompanySettingsWebController extends Controller
{
    use AuthorizesAdminWeb;
    use RespondsAsJson;

    public function index()
    {
        $this->authorizeAdmin('settings.view');

        $perfil = $this->loadOrCreateProfile();

        return view('admin.company-settings.index', [
            'perfil' => $perfil,
            'canManage' => auth()->user()?->can('settings.manage') ?? false,
        ]);
    }

    public function update(Request $request)
    {
        $this->authorizeAdmin('settings.manage');

        try {
            $dados = $request->validate([
                'nomeEmpresa' => ['required', 'string', 'max:255'],
                'nif' => ['nullable', 'string', 'max:32'],
                'email' => ['nullable', 'string', 'max:255'],
                'telefone' => ['nullable', 'string', 'max:64'],
                'endereco' => ['nullable', 'string', 'max:255'],
                'banco' => ['nullable', 'string', 'max:255'],
                'iban' => ['nullable', 'string', 'max:255'],
                'rodapeFacturas' => ['nullable', 'string', 'max:2000'],
            ]);
        } catch (ValidationException $exception) {
            return $this->jsonFromValidation($exception);
        }

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

        return $this->jsonOk(null, __('toasts.company_settings_saved'));
    }

    private function loadOrCreateProfile(): CompanyProfile
    {
        return CompanyProfile::query()->firstOrCreate([], [
            'name' => 'Empresa Demo Lda',
            'nif' => '400000099',
            'email' => 'geral@empresa.co.mz',
            'phone' => '+258 21 000 000',
            'address' => 'Av. 25 de Setembro, 420, Maputo, Moçambique',
            'bank' => 'BCI — Banco Comercial e de Investimentos',
            'iban' => 'MZ59 0000 0000 1234 5678 901',
            'invoice_footer' => 'Obrigado pela sua preferência. Para reclamações contacte: geral@empresa.co.mz',
        ]);
    }
}
