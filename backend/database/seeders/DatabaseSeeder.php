<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Seeder de base : uniquement le compte administrateur.
 * Aucune donnée métier (clients, produits, ventes, crédits) n'est créée.
 *
 * Pour recharger un jeu de données de démonstration :
 *   php artisan db:seed --class=DemoSeeder
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::firstOrCreate(
            ['username' => 'admin'],
            [
                'name' => 'Administrateur',
                'email' => 'admin@credit.local',
                'password' => 'admin123',
                'role' => 'admin',
                'locale' => 'fr',
                'is_active' => true,
            ],
        );
    }
}
