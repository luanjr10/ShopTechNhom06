<?php

use App\Models\ProductImage;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        ProductImage::all()->each(function (ProductImage $productImage) {
            $images = $productImage->images;

            if (! is_string($images)) {
                return;
            }

            $decoded = json_decode($images, true);

            if (is_array($decoded)) {
                $productImage->images = $decoded;
                $productImage->save();
            }
        });
    }

    public function down(): void
    {
    }
};
