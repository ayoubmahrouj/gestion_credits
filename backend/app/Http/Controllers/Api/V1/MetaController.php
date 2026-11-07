<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Category;

class MetaController extends Controller
{
    /**
     * Données de référence pour les formulaires (marques, catégories).
     */
    public function index()
    {
        return response()->json([
            'brands' => Brand::active()->orderBy('name')->get(['id', 'name']),
            'categories' => Category::active()->orderBy('name')->get(['id', 'name', 'parent_id']),
            'payment_methods' => ['cash', 'bank', 'cheque', 'card', 'other'],
            'client_types' => ['individual', 'company'],
            'credit_statuses' => ['active', 'settled', 'overdue', 'cancelled'],
        ]);
    }
}
