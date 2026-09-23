<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SiteSetting;

class SiteSettingController extends Controller
{
    // [GET] /api/settings/flash-sale — public, storefront cần biết giờ kết thúc flash sale.
    public function flashSale()
    {
        return response()->json([
            'success' => true,
            'data' => [
                'ends_at' => SiteSetting::get('flash_sale_ends_at'),
            ],
        ], 200);
    }
}
