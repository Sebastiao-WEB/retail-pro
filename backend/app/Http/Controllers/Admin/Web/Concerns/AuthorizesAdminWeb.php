<?php

namespace App\Http\Controllers\Admin\Web\Concerns;

trait AuthorizesAdminWeb
{
    protected function authorizeAdmin(string $permission): void
    {
        abort_unless(auth()->user()?->can($permission), 403);
    }
}
