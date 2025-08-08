<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Product;

class ProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $products = [
            [
                'name' => 'Gaming Voucher',
                'code' => 'GAMING_VOUCHER',
                'description' => 'Get 20% off on your next game purchase',
                'point_cost' => 500,
                'icon' => 'gamepad',
                'is_active' => true,
                'stock' => -1, // Unlimited
            ],
            [
                'name' => 'Music Subscription',
                'code' => 'MUSIC_SUBSCRIPTION',
                'description' => '1 month free premium music access',
                'point_cost' => 300,
                'icon' => 'music-note',
                'is_active' => true,
                'stock' => -1,
            ],
            [
                'name' => 'Movie Ticket',
                'code' => 'MOVIE_TICKET',
                'description' => 'Free movie ticket for any cinema',
                'point_cost' => 800,
                'icon' => 'film',
                'is_active' => true,
                'stock' => 50,
            ],
            [
                'name' => 'Coffee Voucher',
                'code' => 'COFFEE_VOUCHER',
                'description' => 'Free coffee at any partner cafe',
                'point_cost' => 200,
                'icon' => 'coffee',
                'is_active' => true,
                'stock' => -1,
            ],
            [
                'name' => 'Art Workshop',
                'code' => 'ART_WORKSHOP',
                'description' => 'Free creative workshop session',
                'point_cost' => 600,
                'icon' => 'palette',
                'is_active' => true,
                'stock' => 20,
            ],
            [
                'name' => 'Gym Pass',
                'code' => 'GYM_PASS',
                'description' => '1 week free gym membership',
                'point_cost' => 400,
                'icon' => 'dumbbell',
                'is_active' => true,
                'stock' => 30,
            ],
        ];

        foreach ($products as $product) {
            Product::create($product);
        }
    }
}
