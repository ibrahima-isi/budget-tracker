<?php

namespace App\Http\Controllers;

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

    public function destroy(Categorie $category)
    {
        $user = Auth::user();

        if (!$user->is_admin && $category->user_id !== $user->id) {
            abort(403, 'Action non autorisée.');
        }

        $category->delete();

        return redirect()->route('categories.index')
            ->with('success', 'Catégorie supprimée.');
    }

    public function toggleEnabled(Categorie $category)
    {
        $user = Auth::user();

        // Reject if the category is not visible to this user (IDOR prevention)
        if (!Categorie::visibleFor($user)->where('id', $category->id)->exists()) {
            abort(403, 'Action non autorisée.');
        }

        CategorieUserSetting::updateOrCreate(
            ['user_id' => $user->id, 'categorie_id' => $category->id],
            ['enabled' => !Categorie::enabledFor($user)->where('id', $category->id)->exists()]
        );

        return back();
    }
}
