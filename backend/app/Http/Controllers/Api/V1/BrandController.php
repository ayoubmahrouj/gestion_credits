<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class BrandController extends Controller
{
    public function index(Request $request)
    {
        $query = Brand::withCount('products')
            ->when(! $request->boolean('with_disabled'), fn ($q) => $q->where('is_active', true))
            ->when($request->boolean('only_disabled'), fn ($q) => $q->where('is_active', false))
            ->orderBy('name');

        return response()->json([
            'data' => $query->get(['id', 'name', 'slug', 'is_active']),
        ]);
    }

    public function store(Request $request)
    {
        $brand = Brand::create([
            'name' => trim($this->validatedName($request)),
            'slug' => $this->slugFrom($request),
            'is_active' => true,
        ]);

        return response()->json(['data' => $brand->loadCount('products')], 201);
    }

    public function update(Request $request, Brand $brand)
    {
        $name = trim($this->validatedName($request, $brand));

        $brand->update(['name' => $name, 'slug' => $this->slugFrom($request, $brand)]);

        return response()->json(['data' => $brand->loadCount('products')]);
    }

    /**
     * « Suppression » = désactivation. La marque reste en base et peut être réactivée.
     */
    public function destroy(Brand $brand)
    {
        $brand->update(['is_active' => false]);

        return response()->json(['message' => __('Marque désactivée (les données sont conservées).')]);
    }

    public function restore(Brand $brand)
    {
        $brand->update(['is_active' => true]);

        return response()->json(['data' => $brand->loadCount('products')]);
    }

    private function validatedName(Request $request, ?Brand $brand = null): string
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
        ]);

        $slug = Str::slug($data['name']);

        if ($slug === '') {
            throw ValidationException::withMessages(['name' => __('Nom invalide.')]);
        }

        $exists = Brand::where('slug', $slug)
            ->when($brand, fn ($q) => $q->whereKeyNot($brand->getKey()))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages(['name' => __('Cette marque existe déjà.')]);
        }

        return $data['name'];
    }

    private function slugFrom(Request $request, ?Brand $brand = null): string
    {
        return Str::slug(trim((string) $request->input('name')));
    }
}
