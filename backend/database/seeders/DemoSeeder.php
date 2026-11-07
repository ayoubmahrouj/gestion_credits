<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Client;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use App\Services\PaymentService;
use App\Services\SaleService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Jeu de données de démonstration (optionnel).
 *   php artisan db:seed --class=DemoSeeder
 *
 * ⚠️ À ne pas lancer sur une base de production contenant de vraies données.
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::firstOrCreate(
            ['username' => 'admin'],
            ['name' => 'Administrateur', 'email' => 'admin@credit.local', 'password' => 'admin123', 'role' => 'admin', 'locale' => 'fr', 'is_active' => true],
        );

        User::firstOrCreate(
            ['username' => 'caissier'],
            ['name' => 'Caissier', 'email' => 'caissier@credit.local', 'password' => 'admin123', 'role' => 'cashier', 'locale' => 'ar', 'is_active' => true],
        );

        $brands = collect(['Bosch', 'Valeo', 'Michelin', 'Continental', 'Exide', 'Delphi'])
            ->mapWithKeys(fn ($name) => [$name => Brand::firstOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name],
            )]);

        $categories = collect([
            'Pièces moteur', 'Pneus', 'Batteries', 'Filtration', 'Freinage', 'Éclairage',
        ])->mapWithKeys(fn ($name) => [$name => Category::firstOrCreate(
            ['slug' => Str::slug($name)],
            ['name' => $name],
        )]);

        $products = [
            ['sku' => 'PNE-195-65-15', 'name_fr' => 'Pneu 195/65 R15', 'name_ar' => 'إطار 195/65 R15', 'brand' => 'Michelin', 'category' => 'Pneus', 'purchase' => 620, 'sale' => 850, 'stock' => 40, 'min' => 10],
            ['sku' => 'PNE-205-55-16', 'name_fr' => 'Pneu 205/55 R16', 'name_ar' => 'إطار 205/55 R16', 'brand' => 'Continental', 'category' => 'Pneus', 'purchase' => 780, 'sale' => 1050, 'stock' => 24, 'min' => 8],
            ['sku' => 'BAT-70AH', 'name_fr' => 'Batterie 70Ah', 'name_ar' => 'بطارية 70 أمبير', 'brand' => 'Exide', 'category' => 'Batteries', 'purchase' => 560, 'sale' => 780, 'stock' => 18, 'min' => 5],
            ['sku' => 'BAT-60AH', 'name_fr' => 'Batterie 60Ah', 'name_ar' => 'بطارية 60 أمبير', 'brand' => 'Exide', 'category' => 'Batteries', 'purchase' => 470, 'sale' => 650, 'stock' => 22, 'min' => 5],
            ['sku' => 'FIL-HUI-001', 'name_fr' => 'Filtre à huile', 'name_ar' => 'فلتر الزيت', 'brand' => 'Bosch', 'category' => 'Filtration', 'purchase' => 35, 'sale' => 65, 'stock' => 120, 'min' => 20],
            ['sku' => 'FIL-AIR-002', 'name_fr' => 'Filtre à air', 'name_ar' => 'فلتر الهواء', 'brand' => 'Bosch', 'category' => 'Filtration', 'purchase' => 45, 'sale' => 85, 'stock' => 90, 'min' => 20],
            ['sku' => 'PLQ-FRE-003', 'name_fr' => 'Plaquettes de frein avant', 'name_ar' => 'أقراص الفرامل الأمامية', 'brand' => 'Valeo', 'category' => 'Freinage', 'purchase' => 180, 'sale' => 290, 'stock' => 35, 'min' => 10],
            ['sku' => 'DIS-FRE-004', 'name_fr' => 'Disque de frein', 'name_ar' => 'قرص الفرامل', 'brand' => 'Valeo', 'category' => 'Freinage', 'purchase' => 320, 'sale' => 480, 'stock' => 16, 'min' => 6],
            ['sku' => 'AMP-H7', 'name_fr' => 'Ampoule H7', 'name_ar' => 'مصباح H7', 'brand' => 'Bosch', 'category' => 'Éclairage', 'purchase' => 25, 'sale' => 50, 'stock' => 200, 'min' => 30],
            ['sku' => 'BOU-NGK-4', 'name_fr' => 'Bougies NGK (x4)', 'name_ar' => 'شمعات NGK', 'brand' => 'Delphi', 'category' => 'Pièces moteur', 'purchase' => 120, 'sale' => 190, 'stock' => 60, 'min' => 15],
        ];

        foreach ($products as $row) {
            Product::firstOrCreate(['sku' => $row['sku']], [
                'name_fr' => $row['name_fr'],
                'name_ar' => $row['name_ar'],
                'brand_id' => $brands[$row['brand']]->id,
                'category_id' => $categories[$row['category']]->id,
                'purchase_price' => $row['purchase'],
                'sale_price' => $row['sale'],
                'stock_qty' => $row['stock'],
                'stock_min' => $row['min'],
                'tva' => 20,
            ]);
        }

        $clients = [
            ['name' => 'Garage Atlas', 'type' => 'company', 'phone' => '0661122334', 'ice' => '002345678000012', 'city' => 'Marrakech', 'limit' => 15000],
            ['name' => 'Auto Service Tazi', 'type' => 'company', 'phone' => '0662233445', 'ice' => '003456789000023', 'city' => 'Marrakech', 'limit' => 10000],
            ['name' => 'Mohamed El Amrani', 'type' => 'individual', 'phone' => '0677889900', 'city' => 'Marrakech', 'limit' => 5000],
            ['name' => 'Taxi Bleu 34', 'type' => 'individual', 'phone' => '0611223344', 'city' => 'Marrakech', 'limit' => 3000],
            ['name' => 'Transport Sahara', 'type' => 'company', 'phone' => '0522334455', 'ice' => '004567890000034', 'city' => 'Marrakech', 'limit' => 25000],
        ];

        foreach ($clients as $i => $row) {
            Client::firstOrCreate(['code' => 'CL-'.str_pad((string) ($i + 1), 5, '0', STR_PAD_LEFT)], [
                'name' => $row['name'],
                'type' => $row['type'],
                'phone' => $row['phone'],
                'whatsapp' => $row['phone'],
                'ice' => $row['ice'] ?? null,
                'city' => $row['city'],
                'credit_limit' => $row['limit'],
                'balance' => 0,
                'score' => 50,
                'status' => 'active',
            ]);
        }

        if (Sale::count() === 0) {
            $saleService = app(SaleService::class);
            $paymentService = app(PaymentService::class);

            $p1 = Product::where('sku', 'PNE-195-65-15')->first();
            $p2 = Product::where('sku', 'BAT-70AH')->first();
            $p3 = Product::where('sku', 'FIL-HUI-001')->first();

            $c1 = Client::where('code', 'CL-00001')->first();
            $c2 = Client::where('code', 'CL-00003')->first();

            $saleService->create([
                'client_id' => $c1->id,
                'type' => 'credit',
                'items' => [
                    ['product_id' => $p1->id, 'quantity' => 4],
                    ['product_id' => $p2->id, 'quantity' => 2],
                ],
                'installments' => ['count' => 3, 'first_due_date' => now()->subDays(10)->toDateString(), 'interval_days' => 30],
            ], $admin);

            $saleService->create([
                'client_id' => $c2->id,
                'type' => 'cash',
                'items' => [
                    ['product_id' => $p3->id, 'quantity' => 6],
                ],
                'payment' => ['amount' => 390, 'method' => 'cash'],
            ], $admin);

            $paymentService->register([
                'client_id' => $c1->id,
                'amount' => 1500,
                'method' => 'cash',
                'idempotency_key' => 'seed-payment-1',
            ], $admin);

            $saleService->create([
                'client_id' => $c2->id,
                'type' => 'credit',
                'items' => [
                    ['product_id' => $p2->id, 'quantity' => 1],
                    ['product_id' => $p1->id, 'quantity' => 2],
                ],
                'installments' => ['count' => 2, 'first_due_date' => now()->addDays(15)->toDateString()],
            ], $admin);
        }
    }
}
