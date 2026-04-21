<?php

namespace App\Http\Controllers;

use App\Http\Requests\DeleteCategorieRequest;
use App\Http\Requests\StoreCategorieRequest;
use App\Http\Requests\UpdateCategorieRequest;
use App\Models\Categorie;
use App\Models\CategorieUserSetting;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class CategorieController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        $categories = Categorie::visibleFor($user)
            ->withCount('depenses')
            ->with(['userSettings' => fn ($q) => $q->where('user_id', $user->id)])
            ->orderBy('nom')
            ->get()
            ->map(function ($cat) {
                $setting = $cat->userSettings->first();
                $cat->enabled = $setting ? $setting->enabled : true;
                $cat->unsetRelation('userSettings');
                return $cat;
            });

        return Inertia::render('Categories/Index', [
            'categories' => $categories,
        ]);
    }

    public function store(StoreCategorieRequest $request)
    {
        $user = Auth::user();

        Categorie::create([
            ...$request->validated(),
            'user_id' => $user->is_admin ? null : $user->id,
        ]);

        return redirect()->route('categories.index')
            ->with('success', 'Catégorie créée.');
    }

    public function update(UpdateCategorieRequest $request, Categorie $category)
    {
        // Authorization is handled in UpdateCategorieRequest::authorize()
        $category->update($request->validated());

        return redirect()->route('categories.index')
            ->with('success', 'Catégorie mise à jour.');
    }

    public function destroy(DeleteCategorieRequest $request, Categorie $category)
    {
        // Authorization is handled in DeleteCategorieRequest::authorize()
        $category->delete();

        return redirect()->route('categories.index')
            ->with('success', 'Catégorie supprimée.');
    }

    public function toggleEnabled(Categorie $category)
    {
        $user = Auth::user();

        // Query 1: load existing setting or build an unsaved instance
        $setting = CategorieUserSetting::firstOrNew([
            'user_id'      => $user->id,
            'categorie_id' => $category->id,
        ]);

        // For a brand-new setting, verify the category is visible to this user (IDOR prevention).
        // If a setting row already exists, the category was visible when it was created and
        // cascade deletes ensure the row is gone if the category was deleted.
        if (!$setting->exists && !Categorie::visibleFor($user)->where('id', $category->id)->exists()) {
            abort(403, 'Action non autorisée.');
        }

        // Default state is "enabled"; first toggle of a new setting means disabling.
        $setting->enabled = $setting->exists ? !$setting->enabled : false;

        // Query 2: INSERT or UPDATE
        $setting->save();

        return back();
    }
}
