<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CompanyProfile;
use Illuminate\Http\Request;

class CompanyProfileController extends Controller
{
    public function show()
    {
        $profile = CompanyProfile::query()->firstOrCreate(
            [],
            [
                'name' => 'Empresa Demo Lda',
                'nif' => '400000099',
                'email' => 'geral@empresa.co.mz',
                'phone' => '+258 21 000 000',
                'address' => 'Av. 25 de Setembro, 420, Maputo, Moçambique',
                'bank' => 'BCI — Banco Comercial e de Investimentos',
                'iban' => 'MZ59 0000 0000 1234 5678 901',
            ]
        );

        return response()->json([
            'data' => [
                'nomeEmpresa' => $profile->name,
                'nif' => $profile->nif ?? '',
                'email' => $profile->email ?? '',
                'telefone' => $profile->phone ?? '',
                'endereco' => $profile->address ?? '',
                'banco' => $profile->bank ?? '',
                'iban' => $profile->iban ?? '',
            ],
        ]);
    }

    public function update(Request $request)
    {
        $dados = $request->validate([
            'nomeEmpresa' => ['required', 'string', 'max:255'],
            'nif' => ['nullable', 'string', 'max:32'],
            'email' => ['nullable', 'string', 'max:255'],
            'telefone' => ['nullable', 'string', 'max:64'],
            'endereco' => ['nullable', 'string', 'max:255'],
            'banco' => ['nullable', 'string', 'max:255'],
            'iban' => ['nullable', 'string', 'max:255'],
        ]);

        $profile = CompanyProfile::query()->firstOrCreate([]);
        $profile->fill([
            'name' => $dados['nomeEmpresa'],
            'nif' => $dados['nif'] ?? '',
            'email' => $dados['email'] ?? '',
            'phone' => $dados['telefone'] ?? '',
            'address' => $dados['endereco'] ?? '',
            'bank' => $dados['banco'] ?? '',
            'iban' => $dados['iban'] ?? '',
        ])->save();

        return response()->json([
            'message' => 'Dados da empresa atualizados.',
            'data' => [
                'nomeEmpresa' => $profile->name,
                'nif' => $profile->nif ?? '',
                'email' => $profile->email ?? '',
                'telefone' => $profile->phone ?? '',
                'endereco' => $profile->address ?? '',
                'banco' => $profile->bank ?? '',
                'iban' => $profile->iban ?? '',
            ],
        ]);
    }
}

