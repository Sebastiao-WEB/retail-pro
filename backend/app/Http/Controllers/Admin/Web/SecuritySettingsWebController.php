<?php

namespace App\Http\Controllers\Admin\Web;

use App\Http\Controllers\Controller;

class SecuritySettingsWebController extends Controller
{
    public function index()
    {
        $user = auth()->user();

        return view('admin.security.index', [
            'twoFactorConfirmed' => ! is_null($user->two_factor_confirmed_at),
            'twoFactorPending' => ! is_null($user->two_factor_secret) && is_null($user->two_factor_confirmed_at),
        ]);
    }
}
