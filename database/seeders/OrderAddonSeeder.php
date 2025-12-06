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
                'title' => 'Explainer Video',
                'sub_title' => '2D Animation',
                'description' => 'Engaging 2-minute 2D animated video to communicate your product or service. Includes scriptwriting, storyboarding, voice-over and full animation.',
                'price' => 299.99,
                'status' => 'active',
            ],
            [
                'title' => 'Promotional Video',
                'sub_title' => 'Brand Story',
                'description' => 'Up to 60-second animated brand story or social media promo. Clean visuals, up to 4K resolution, custom characters and motion graphics.',
                'price' => 499.00,
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
                'price' => 299.00,
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


