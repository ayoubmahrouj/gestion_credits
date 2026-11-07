<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $perPage = min(100, max(1, (int) $request->integer('per_page', 20)));

        $query = Product::query()
            ->with(['brand', 'category'])
            ->when($request->filled('search'), function ($q) use ($request) {
                $s = '%'.$request->string('search')->toString().'%';
                $q->where(fn ($w) => $w->where('name_fr', 'like', $s)
                    ->orWhere('name_ar', 'like', $s)
                    ->orWhere('sku', 'like', $s)
                    ->orWhere('barcode', 'like', $s));
            })
            ->when($request->filled('brand_id'), fn ($q) => $q->where('brand_id', $request->integer('brand_id')))
            ->when($request->filled('category_id'), fn ($q) => $q->where('category_id', $request->integer('category_id')))
            ->when($request->boolean('low_stock'), fn ($q) => $q->lowStock())
            ->when(! $request->boolean('with_disabled'), fn ($q) => $q->where('is_active', true))
            ->when($request->boolean('only_disabled'), fn ($q) => $q->where('is_active', false))
            ->orderBy('name_fr');

        return ProductResource::collection($query->paginate($perPage)->withQueryString());
    }

    public function store(StoreProductRequest $request)
    {
        $data = $request->validated();

        $data['sku'] = filled($data['sku'] ?? null) ? $data['sku'] : $this->nextSku();
        $data['sale_price'] = $data['sale_price'] ?? 0;

        $product = Product::create([...$data, 'is_active' => true]);

        if ((float) $product->stock_qty > 0) {
            StockMovement::create([
                'product_id' => $product->id,
                'type' => 'in',
                'quantity' => $product->stock_qty,
                'unit_cost' => $product->purchase_price,
                'reason' => 'Stock initial',
                'user_id' => $request->user()?->id,
            ]);
        }

        return (new ProductResource($product->load(['brand', 'category'])))->response()->setStatusCode(201);
    }

    /**
     * Référence générée automatiquement quand elle n'est pas saisie.
     */
    private function nextSku(): string
    {
        $next = (int) (Product::withTrashed()->max('id') ?? 0) + 1;

        do {
            $sku = 'PRD-'.str_pad((string) $next, 5, '0', STR_PAD_LEFT);
            $next++;
        } while (Product::withTrashed()->where('sku', $sku)->exists());

        return $sku;
    }

    public function show(Product $product)
    {
        $product->load(['brand', 'category']);

        return response()->json([
            'data' => new ProductResource($product),
            'movements' => $product->stockMovements()->latest()->limit(30)->get(),
        ]);
    }

    public function update(StoreProductRequest $request, Product $product)
    {
        $product->update($request->validated());

        return new ProductResource($product->load(['brand', 'category']));
    }

    public function destroy(Product $product)
    {
        $product->update(['is_active' => false]);

        return response()->json(['message' => __('Produit désactivé (les données sont conservées).')]);
    }

    public function restore(Product $product)
    {
        $product->update(['is_active' => true]);

        return new ProductResource($product->load(['brand', 'category']));
    }
}
