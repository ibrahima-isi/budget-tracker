<?php

namespace App\Policies;

use App\Models\Revenu;
use App\Models\User;

class RevenuPolicy
{
    public function update(User $user, Revenu $revenu): bool
    {
        return $user->id === $revenu->user_id;
    }

    public function delete(User $user, Revenu $revenu): bool
    {
        return $user->id === $revenu->user_id;
    }
}
