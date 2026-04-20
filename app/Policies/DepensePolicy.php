<?php

namespace App\Policies;

use App\Models\Depense;
use App\Models\User;

class DepensePolicy
{
    public function update(User $user, Depense $depense): bool
    {
        return $user->id === $depense->user_id;
    }

    public function delete(User $user, Depense $depense): bool
    {
        return $user->id === $depense->user_id;
    }
}
