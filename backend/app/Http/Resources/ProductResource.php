<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'sku' => $this->sku,
            'barcode' => $this->barcode,
            'name_fr' => $this->name_fr,
            'name_ar' => $this->name_ar,
            'description' => $this->description,
            'brand' => $this->whenLoaded('brand', fn () => $this->brand?->only(['id', 'name'])),
            'category' => $this->whenLoaded('category', fn () => $this->category?->only(['id', 'name'])),
            'brand_id' => $this->brand_id,
            'category_id' => $this->category_id,
            'unit' => $this->unit,
            'purchase_price' => (float) $this->purchase_price,
            'sale_price' => (float) $this->sale_price,
            'tva' => (float) $this->tva,
            'stock_qty' => (float) $this->stock_qty,
            'stock_min' => (float) $this->stock_min,
            'is_low_stock' => $this->isLowStock(),
            'is_active' => (bool) $this->is_active,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
