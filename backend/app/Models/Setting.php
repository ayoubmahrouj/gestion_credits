<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['key', 'value', 'group'])]
class Setting extends Model
{
    public const DEFAULTS = [
        'shop_name' => 'Gestion Crédits',
        'shop_tagline' => 'Pièces auto · Pneus · Batteries',
        'shop_phone' => '',
        'shop_address' => '',
        'shop_ice' => '',
        'ticket_footer' => 'Merci de votre confiance',
        'currency' => 'DH',
    ];

    /**
     * Toutes les valeurs (valeurs enregistrées + valeurs par défaut).
     *
     * @return array<string, string|null>
     */
    public static function allValues(): array
    {
        $stored = static::query()->pluck('value', 'key')->all();

        $clean = array_filter($stored, fn ($value) => $value !== null);

        return array_merge(self::DEFAULTS, $clean);
    }

    public static function put(string $key, ?string $value): void
    {
        static::query()->updateOrCreate(['key' => $key], ['value' => $value, 'group' => 'shop']);
    }
}
