<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCategorieRequest;
use App\Http\Requests\UpdateCategorieRequest;
use App\Models\Categorie;
use Inertia\Inertia;

class CategorieController extends Controller
{
    public function index()
    {
        $categories = Categorie::withCount('depenses')->orderBy('nom')->get();

        return Inertia::render('Categories/Index', [
            'categories' => $categories,
        ]);
    }

    public function store(StoreCategorieRequest $request)
    {
        Categorie::create($request->validated());

        return redirect()->route('categories.index')
            ->with('success', 'Catégorie créée.');
    }

    public function update(UpdateCategorieRequest $request, Categorie $category)
    {
        $category->update($request->validated());

        return redirect()->route('categories.index')
            ->with('success', 'Catégorie mise à jour.');
    }

    public function destroy(Categorie $category)
    {
        $category->delete();

        return redirect()->route('categories.index')
            ->with('success', 'Catégorie supprimée.');
    }
}
