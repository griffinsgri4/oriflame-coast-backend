<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CategorySeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $defaults = [
            'Skincare',
            'Makeup',
            'Fragrance',
            'Wellness',
            'Men',
            'Hair Care',
            'Body Care',
        ];

        foreach ($defaults as $idx => $name) {
            $slug = Str::slug($name);
            $exists = DB::table('categories')->where('slug', $slug)->exists();
            if (!$exists) {
                DB::table('categories')->insert([
                    'name' => $name,
                    'slug' => $slug,
                    'order' => $idx,
                    'thumbnail_url' => null,
                    'meta' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }
}