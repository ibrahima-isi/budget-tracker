<?php

namespace App\Http\Controllers;

use App\Http\Requests\DeleteCategoryRequest;
use App\Http\Requests\StoreCategoryRequest;
use App\Http\Requests\UpdateCategoryRequest;
use App\Models\Category;
use App\Models\CategoryUserSetting;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class CategoryController extends Controller
{
    private const PER_PAGE = 24;

    public function index()
    {
        $user = Auth::user();

        $categories = Category::visibleFor($user)
            ->withCount('expenses')
            ->with(['userSettings' => fn ($q) => $q->where('user_id', $user->id)])
            ->orderBy('name')
            ->paginate(self::PER_PAGE)
            ->through(function ($cat) {
                $setting = $cat->userSettings->first();
                $cat->enabled = $setting ? $setting->enabled : true;
                $cat->unsetRelation('userSettings');

                return $cat;
            })
            ->withQueryString();

        return Inertia::render('Categories/Index', [
            'categories' => $categories,
        ]);
    }

    public function store(StoreCategoryRequest $request)
    {
        $user = Auth::user();

        Category::create([
            ...$request->validated(),
            'user_id' => $user->is_admin ? null : $user->id,
        ]);

        return redirect()->route('categories.index')
            ->with('success', __('flash.category_created'));
    }

    public function update(UpdateCategoryRequest $request, Category $category)
    {
        $category->update($request->validated());

        return redirect()->route('categories.index')
            ->with('success', __('flash.category_updated'));
    }

    public function destroy(DeleteCategoryRequest $request, Category $category)
    {
        $category->delete();

        return redirect()->route('categories.index')
            ->with('success', __('flash.category_deleted'));
    }

    public function toggleEnabled(Category $category)
    {
        $user = Auth::user();

        $setting = CategoryUserSetting::firstOrNew([
            'user_id' => $user->id,
            'category_id' => $category->id,
        ]);

        if (! $setting->exists && ! Category::visibleFor($user)->where('id', $category->id)->exists()) {
            abort(403, 'Action non autorisée.');
        }

        $setting->enabled = $setting->exists ? ! $setting->enabled : false;

        try {
            $setting->save();
        } catch (UniqueConstraintViolationException) {
            CategoryUserSetting::where([
                'user_id' => $user->id,
                'category_id' => $category->id,
            ])->update(['enabled' => false]);
        }

        return back();
    }
}
