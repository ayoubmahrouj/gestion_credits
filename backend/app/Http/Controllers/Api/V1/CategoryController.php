<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CategoryController extends Controller
{
    public function index(Request $request)
    {
        $query = Category::withCount('products')
            ->when(! $request->boolean('with_disabled'), fn ($q) => $q->where('is_active', true))
            ->when($request->boolean('only_disabled'), fn ($q) => $q->where('is_active', false))
            ->orderBy('name');

        return response()->json([
            'data' => $query->get(['id', 'name', 'slug', 'parent_id', 'is_active']),
        ]);
    }

    public function store(Request $request)
    {
        $category = Category::create([
            'name' => trim($this->validatedName($request)),
            'slug' => $this->slugFrom($request),
            'is_active' => true,
        ]);

        return response()->json(['data' => $category->loadCount('products')], 201);
    }

    public function update(Request $request, Category $category)
    {
        $category->update([
            'name' => trim($this->validatedName($request, $category)),
            'slug' => $this->slugFrom($request, $category),
        ]);

        return response()->json(['data' => $category->loadCount('products')]);
    }

    /**
     * « Suppression » = désactivation. La catégorie reste en base et peut être réactivée.
     */
    public function destroy(Category $category)
    {
        $category->update(['is_active' => false]);

        return response()->json(['message' => __('Catégorie désactivée (les données sont conservées).')]);
    }

    public function restore(Category $category)
    {
        $category->update(['is_active' => true]);

        return response()->json(['data' => $category->loadCount('products')]);
    }

    private function validatedName(Request $request, ?Category $category = null): string
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
        ]);

        $slug = Str::slug($data['name']);

        if ($slug === '') {
            throw ValidationException::withMessages(['name' => __('Nom invalide.')]);
        }

        $exists = Category::where('slug', $slug)
            ->when($category, fn ($q) => $q->whereKeyNot($category->getKey()))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages(['name' => __('Cette catégorie existe déjà.')]);
        }

        return $data['name'];
    }

    private function slugFrom(Request $request, ?Category $category = null): string
    {
        return Str::slug(trim((string) $request->input('name')));
    }
}
