<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Product;
use App\Models\Stock;

class ProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Define some sample products based on the frontend components
        $products = [
            [
                'name' => 'NovAge Ecollagen Wrinkle Power Set',
                'description' => 'Advanced anti-aging skincare set that corrects wrinkles and refines skin texture. Powered by patented Tri-Peptide Technology, Low Molecular Weight Hyaluronic Acid and Edelweiss Plant Stem Cell extract.',
                'short_description' => 'Complete anti-aging routine for wrinkle reduction.',
                'price' => 12500.00,
                'sale_price' => 9999.00,
                'category' => 'Skincare',
                'brand' => 'NovAge',
                'sku' => 'NVG-001',
                'image' => '/storage/products/novage-set.jpg',
                'featured' => true,
                'status' => 'active',
                'stock' => 50,
            ],
            [
                'name' => 'Giordani Gold Iconic Lipstick',
                'description' => 'Luxurious, satin-finish lipstick infused with restorative Argan Oil for lasting comfort and superior pigment.',
                'short_description' => 'Rich color, satin finish, infused with Argan Oil.',
                'price' => 1800.00,
                'sale_price' => 1450.00,
                'category' => 'Makeup',
                'brand' => 'Giordani Gold',
                'sku' => 'GG-LIP-002',
                'image' => '/storage/products/giordani-lipstick.jpg',
                'featured' => true,
                'status' => 'active',
                'stock' => 100,
            ],
            [
                'name' => 'Love Nature Tea Tree & Lime Set',
                'description' => 'Purifying care for oily skin. Contains organic Tea Tree essential oil and super ingredient Lime.',
                'short_description' => 'Purifying face care for oily skin.',
                'price' => 2500.00,
                'sale_price' => 1950.00,
                'category' => 'Skincare',
                'brand' => 'Love Nature',
                'sku' => 'LN-TT-003',
                'image' => '/storage/products/love-nature-set.jpg',
                'featured' => true,
                'status' => 'active',
                'stock' => 75,
            ],
            [
                'name' => 'Possess Man Eau de Toilette',
                'description' => 'Charismatic fragrance with fresh grapefruit, laurel oil, and orris notes. For the man who is destined to possess the world.',
                'short_description' => 'Charismatic woody fragrance for men.',
                'price' => 4500.00,
                'sale_price' => 3200.00,
                'category' => 'Fragrance',
                'brand' => 'Possess',
                'sku' => 'POS-MAN-004',
                'image' => '/storage/products/possess-man.jpg',
                'featured' => true,
                'status' => 'active',
                'stock' => 30,
            ],
            [
                'name' => 'Milk & Honey Gold Shampoo',
                'description' => 'Creamy shampoo with organic sourced Milk and Honey extracts. Gently cleanses and nourishes hair.',
                'short_description' => 'Nourishing shampoo with organic extracts.',
                'price' => 950.00,
                'sale_price' => null,
                'category' => 'Hair Care',
                'brand' => 'Milk & Honey',
                'sku' => 'MH-SHM-005',
                'image' => '/storage/products/milk-honey-shampoo.jpg',
                'featured' => false,
                'status' => 'active',
                'stock' => 120,
            ],
            [
                'name' => 'The ONE Tremendous Mascara',
                'description' => 'Extreme volume mascara for a bigger, bolder lash look. Water-resistant formula.',
                'short_description' => 'Volume boosting mascara.',
                'price' => 1200.00,
                'sale_price' => 890.00,
                'category' => 'Makeup',
                'brand' => 'The ONE',
                'sku' => 'ONE-MSC-006',
                'image' => '/storage/products/one-mascara.jpg',
                'featured' => true,
                'status' => 'active',
                'stock' => 200,
            ],
             [
                'name' => 'Amber Elixir Eau de Parfum',
                'description' => 'Bask in the golden warmth of precious amber. An opulent, oriental woody fragrance.',
                'short_description' => 'Sensual oriental woody fragrance.',
                'price' => 3800.00,
                'sale_price' => 2900.00,
                'category' => 'Fragrance',
                'brand' => 'Amber Elixir',
                'sku' => 'AMB-ELX-007',
                'image' => '/storage/products/amber-elixir.jpg',
                'featured' => true,
                'status' => 'active',
                'stock' => 45,
            ],
            [
                'name' => 'Optimals Hydra Radiance Set',
                'description' => 'Hydrating skincare routine for dehydrated skin. Formulated with Swedish natural ingredient blends.',
                'short_description' => 'Hydration boost for radiant skin.',
                'price' => 6500.00,
                'sale_price' => 5200.00,
                'category' => 'Skincare',
                'brand' => 'Optimals',
                'sku' => 'OPT-HYD-008',
                'image' => '/storage/products/optimals-set.jpg',
                'featured' => false,
                'status' => 'active',
                'stock' => 60,
            ]
        ];

        foreach ($products as $data) {
            $stockQty = $data['stock'];
            unset($data['stock']);

            $sku = $data['sku'] ?? null;

            if ($sku) {
                $product = Product::updateOrCreate(['sku' => $sku], $data);
            } else {
                $product = Product::create($data);
            }

            Stock::updateOrCreate(
                ['product_id' => $product->id],
                ['quantity' => $stockQty]
            );
        }
    }
}
