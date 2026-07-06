<?php

namespace Database\Seeders;

use App\Models\MarketingCard;
use Illuminate\Database\Seeder;

class MarketingCardSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        MarketingCard::truncate();

        // Insert sample marketing cards
        MarketingCard::create([
            'tag' => 'PROMO',
            'title' => 'Combo Lunch Offer',
            'description' => 'Enjoy a delicious veg combo at 20% off!',
            'price' => '₹150',
            'menu_item_id' => null,
            'video_url' => null,
            'image_path' => 'hero_food_banner.png',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        MarketingCard::create([
            'tag' => 'NEW',
            'title' => 'Family Feast',
            'description' => 'A special feast for the whole family, includes 5 dishes.',
            'price' => '₹500',
            'menu_item_id' => null,
            'video_url' => null,
            'image_path' => 'hero_food_banner.png',
            'is_active' => true,
            'sort_order' => 2,
        ]);
    }
}
?>
