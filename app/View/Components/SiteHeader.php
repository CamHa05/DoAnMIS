<?php

namespace App\View\Components;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\Component;
use Illuminate\View\View;

class SiteHeader extends Component
{
    public readonly ?User $currentUser;

    public readonly ?string $displayName;

    public function __construct()
    {
        $authenticatedUser = Auth::user();
        $this->currentUser = $authenticatedUser instanceof User
            ? $authenticatedUser
            : null;
        $this->displayName = $this->currentUser
            ? ($this->currentUser->full_name ?: $this->currentUser->email)
            : null;
    }

    public function render(): View
    {
        return view('components.site-header');
    }
}
