<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('original_price', 10, 2)->nullable()->after('price');
            $table->decimal('sale_price', 10, 2)->nullable()->after('original_price');
            $table->text('short_description')->nullable()->after('description');
            $table->string('brand')->nullable()->after('category');
            $table->json('tags')->nullable()->after('brand');
            $table->json('gallery')->nullable()->after('image');
            $table->text('how_to_use')->nullable()->after('short_description');
            $table->text('ingredients')->nullable()->after('how_to_use');
            $table->string('weight')->nullable()->after('attributes');
            // Dimensions can be stored inside attributes JSON for flexibility
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn([
                'original_price',
                'sale_price',
                'short_description',
                'brand',
                'tags',
                'gallery',
                'how_to_use',
                'ingredients',
                'weight',
            ]);
        });
    }
};