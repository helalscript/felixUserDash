<?php

namespace Database\Seeders;

use App\Models\Addon;
use Illuminate\Database\Seeder;

class OrderAddonSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $addons = [
            [
                'title' => 'Complete Social Media Creatives ',
                'sub_title' => '2D Animation',
                'description' => 'We will create additional versions of your delivered content in 9:16 vertical and 1:1 square formats, optimized for reels, shorts, and social posts.
                Ideal for expanding reach across multiple platforms without redesigning the main creative.',
                'price' => 99.00,
                'status' => 'active',
            ],
            [
                'title' => 'Captions & Subtitles',
                'sub_title' => 'Brand Story',
                'description' => 'We will add on-screen captions built for mobile viewing to increase retention and reach on social platforms. This helps viewers engage even without sound and improves watch-through rates.',
                'price' => 45.00,
                'status' => 'active',
            ],
            [
                'title' => 'Logo Animation',
                'sub_title' => 'Logo Animation & Motion Identity',
                'description' => 'Bring your brand mark to life with a short (5–10 second) motion version of your logo — perfect for intros, outros and brand reveals.',
                'price' => 149.00,
                'status' => 'active',
            ],
            [
                'title' => 'Illustration & Asset',
                'sub_title' => 'Illustration & Asset Package',
                'description' => 'Full set of vector characters, icons, backgrounds and design assets for use in animation or web. Ideal if you prefer in-house animation but need the art assets.',
                'price' => 199.00,
                'status' => 'active',
            ],
        ];

        foreach ($addons as $data) {
            Addon::updateOrCreate(
                ['title' => $data['title']],
                $data
            );
        }
    }
}
