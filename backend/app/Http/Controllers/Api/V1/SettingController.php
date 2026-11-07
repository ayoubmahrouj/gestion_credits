<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    public function index()
    {
        return response()->json(['data' => Setting::allValues()]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'shop_name' => ['nullable', 'string', 'max:120'],
            'shop_tagline' => ['nullable', 'string', 'max:160'],
            'shop_phone' => ['nullable', 'string', 'max:40'],
            'shop_address' => ['nullable', 'string', 'max:200'],
            'shop_ice' => ['nullable', 'string', 'max:40'],
            'ticket_footer' => ['nullable', 'string', 'max:200'],
            'currency' => ['nullable', 'string', 'max:10'],
        ]);

        foreach ($data as $key => $value) {
            Setting::put($key, $value === null ? null : (string) $value);
        }

        return response()->json(['data' => Setting::allValues()]);
    }
}
