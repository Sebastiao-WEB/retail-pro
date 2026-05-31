<?php

namespace App\Livewire\Admin;

use Livewire\Component;

class SecuritySettingsPage extends Component
{
    public function render()
    {
        $user = auth()->user();

        return view('livewire.admin.security-settings-page', [
            'twoFactorConfirmed' => ! is_null($user->two_factor_confirmed_at),
            'twoFactorPending' => ! is_null($user->two_factor_secret) && is_null($user->two_factor_confirmed_at),
        ])->layout('components.layouts.desktop', [
            'title' => __('auth.security.title'),
        ]);
    }
}
