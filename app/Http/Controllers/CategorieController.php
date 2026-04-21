<?php

namespace App\Http\Controllers;

use App\Http\Requests\DeleteCategorieRequest;
use App\Http\Requests\StoreCategorieRequest;
use App\Http\Requests\UpdateCategorieRequest;
use App\Models\Categorie;
use App\Models\CategorieUserSetting;
use Illuminate\Database\UniqueConstraintViolationException;
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
        $category->update($request->validated());

        return redirect()->route('categories.index')
            ->with('success', 'Catégorie mise à jour.');
    }

    public function destroy(DeleteCategorieRequest $request, Categorie $category)
    {
        $category->delete();

        return redirect()->route('categories.index')
            ->with('success', 'Catégorie supprimée.');
    }

    public function toggleEnabled(Categorie $category)
    {
        $user = Auth::user();

        // Query 1: load existing setting or build an unsaved instance.
        $setting = CategorieUserSetting::firstOrNew([
            'user_id'      => $user->id,
            'categorie_id' => $category->id,
        ]);

        // IDOR prevention: only check visibility when no setting row exists yet.
        // When a row already exists the category is guaranteed visible because:
        //   (a) we enforce visibility before creating any setting row (see below), and
        //   (b) categorie_user_settings.categorie_id cascades on delete, so orphan rows
        //       are impossible.
        // NOTE: this invariant relies on user_id NOT being an editable field on Categorie.
        //       If a category-reassignment feature is ever added, revisit this check.
        if (!$setting->exists && !Categorie::visibleFor($user)->where('id', $category->id)->exists()) {
            abort(403, 'Action non autorisée.');
        }

        // Default state is "enabled"; first toggle of a new setting means disabling.
        $setting->enabled = $setting->exists ? !$setting->enabled : false;

        // Query 2: INSERT or UPDATE.
        // Guard against the extremely unlikely race where two simultaneous requests
        // both see $setting->exists = false and both attempt the INSERT.
        try {
            $setting->save();
        } catch (UniqueConstraintViolationException) {
            // The concurrent request already created the row (default = enabled → disabling).
            CategorieUserSetting::where([
                'user_id'      => $user->id,
                'categorie_id' => $category->id,
            ])->update(['enabled' => false]);
        }

        return back();
    }
}
