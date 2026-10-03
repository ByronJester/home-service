<?php

namespace Database\Seeders;

use App\Models\Promo;
use Illuminate\Database\Seeder;

class PromoSeeder extends Seeder
{
    /**
     * Seed the default landing-page promos.
     */
    public function run(): void
    {
        $promos = [
            [
                'title' => 'Birthday Promo',
                'description' => 'Celebrate your birthday with a custom piece that feels as personal as the memory behind it.',
                'discount' => 10,
                'requirements' => ['Available in birthday month', 'Valid ID With Printed Birthdate'],
                'usage' => 'Limited Availability',
                'image' => '/storage/images/promos/birthday.png',
                'is_active' => true,
            ],
            [
                'title' => 'Halloween Promo',
                'description' => 'Add a bold seasonal statement to your look with a dramatic design that stands out.',
                'discount' => 10,
                'requirements' => ['Available in month of November'],
                'usage' => 'Limited Availability',
                'image' => '/storage/images/promos/halloween.png',
                'is_active' => true,
            ],
            [
                'title' => 'Christmas Promo',
                'description' => 'Give your holiday season a personal edge with a design that makes every celebration memorable.',
                'discount' => 20,
                'requirements' => ['Available in month of December'],
                'usage' => 'Limited Availability',
                'image' => '/storage/images/promos/christmas.png',
                'is_active' => true,
            ],
            [
                'title' => 'Free Minimalist Bonus',
                'description' => 'Avail 2 foot size tattoo freebies of one minimalist tattoo for a clean, thoughtful statement.',
                'discount' => 0,
                'requirements' => ['Avail 2 Foot Size Tattoo'],
                'usage' => 'Limited Availability',
                'image' => '/storage/images/promos/minimalist.png',
                'is_active' => true,
            ],
        ];

        foreach ($promos as $promo) {
            Promo::query()->updateOrCreate(
                ['title' => $promo['title']],
                $promo,
            );
        }
    }
}
