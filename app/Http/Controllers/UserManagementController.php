<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class UserManagementController extends Controller
{
    public function index(): Response
    {
        $users = User::query()
            ->latest('created_at')
            ->paginate(20)
            ->through(function (User $user) {
                $displayUser = User::usesEncryptedStorage()
                    ? User::findDecrypted($user->id) ?? $user
                    : $user;

                return [
                    'id' => $displayUser->id,
                    'name' => $displayUser->name,
                    'email' => $displayUser->email,
                    'is_admin' => (bool) $displayUser->is_admin,
                    'is_approved' => (bool) $displayUser->is_approved,
                    'approved_at' => $displayUser->approved_at?->toISOString(),
                    'created_at' => $displayUser->created_at?->toISOString(),
                ];
            });

        return Inertia::render('Settings/Users', [
            'users' => $users,
        ]);
    }

    public function approve(User $user): RedirectResponse
    {
        $user->approve(request()->user());

        return redirect()
            ->route('settings.users.index')
            ->with('success', 'Utilisateur approuvé.');
    }

    public function revokeApproval(User $user): RedirectResponse
    {
        if ($user->is(request()->user())) {
            return redirect()
                ->route('settings.users.index')
                ->with('error', 'Vous ne pouvez pas retirer votre propre approbation.');
        }

        if ($user->is_admin && ! $this->hasAnotherApprovedAdmin($user)) {
            return redirect()
                ->route('settings.users.index')
                ->with('error', 'Impossible de retirer l’approbation du dernier administrateur actif.');
        }

        $user->revokeApproval();

        return redirect()
            ->route('settings.users.index')
            ->with('success', 'Approbation retirée.');
    }

    private function hasAnotherApprovedAdmin(User $user): bool
    {
        return User::query()
            ->where('is_admin', true)
            ->where('is_approved', true)
            ->whereKeyNot($user->id)
            ->exists();
    }
}
