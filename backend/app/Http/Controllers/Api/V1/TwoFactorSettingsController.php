<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Actions\ConfirmTwoFactorAuthentication;
use Laravel\Fortify\Actions\DisableTwoFactorAuthentication;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;
use Laravel\Fortify\Actions\GenerateNewRecoveryCodes;

class TwoFactorSettingsController extends Controller
{
    public function status(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'enabled' => ! is_null($user->two_factor_confirmed_at),
            'pending' => ! is_null($user->two_factor_secret) && is_null($user->two_factor_confirmed_at),
        ]);
    }

    public function enable(Request $request, EnableTwoFactorAuthentication $enable)
    {
        $request->validate([
            'password' => ['required', 'string'],
        ]);

        $this->verifyPassword($request);

        $enable($request->user(), $request->boolean('force', false));

        return response()->json([
            'message' => 'Leia o QR code e confirme com um código da aplicação.',
            'pending' => true,
        ]);
    }

    public function confirm(Request $request, ConfirmTwoFactorAuthentication $confirm)
    {
        $request->validate([
            'code' => ['required', 'string'],
        ]);

        $confirm($request->user(), $request->input('code'));

        return response()->json([
            'message' => 'Autenticação em dois factores confirmada.',
            'enabled' => true,
        ]);
    }

    public function disable(Request $request, DisableTwoFactorAuthentication $disable)
    {
        $request->validate([
            'password' => ['required', 'string'],
        ]);

        $this->verifyPassword($request);
        $disable($request->user());

        return response()->json([
            'message' => 'Autenticação em dois factores desactivada.',
            'enabled' => false,
        ]);
    }

    public function qrCode(Request $request)
    {
        $user = $request->user();

        if (is_null($user->two_factor_secret)) {
            return response()->json(['message' => '2FA ainda não foi activado.'], 422);
        }

        return response()->json([
            'svg' => $user->twoFactorQrCodeSvg(),
            'url' => $user->twoFactorQrCodeUrl(),
        ]);
    }

    public function recoveryCodes(Request $request)
    {
        $request->validate([
            'password' => ['required', 'string'],
        ]);

        $this->verifyPassword($request);

        if (is_null($request->user()->two_factor_confirmed_at)) {
            return response()->json([
                'message' => 'Autenticação em dois factores não está confirmada.',
            ], 422);
        }

        return response()->json([
            'recovery_codes' => $request->user()->recoveryCodes(),
        ]);
    }

    public function regenerateRecoveryCodes(Request $request, GenerateNewRecoveryCodes $generate)
    {
        $request->validate([
            'password' => ['required', 'string'],
        ]);

        $this->verifyPassword($request);

        if (is_null($request->user()->two_factor_confirmed_at)) {
            return response()->json([
                'message' => 'Autenticação em dois factores não está confirmada.',
            ], 422);
        }

        $generate($request->user());

        return response()->json([
            'message' => 'Novos códigos de recuperação gerados.',
            'recovery_codes' => $request->user()->recoveryCodes(),
        ]);
    }

    private function verifyPassword(Request $request): void
    {
        if (! Hash::check($request->input('password'), $request->user()->password)) {
            throw ValidationException::withMessages([
                'password' => ['Senha incorrecta.'],
            ]);
        }
    }
}
