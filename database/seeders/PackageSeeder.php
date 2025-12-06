<?php

namespace Database\Seeders;

use App\Models\Package;
use Illuminate\Database\Seeder;

class PackageSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $packages = [
            [
                'title' => 'Launch',
                'subtitle' => null,
                'description' => 'Startups & small businesses ready to make their debut.',
                'price' => 299.99,
                'explore_link' => 'https://www.youtube.com/playlist?list=PLyoobzILxORFuRwZIc2ogpJuVAKvPrZa6',
                'specification_text' => null,
                'features' => [
                    'Standard 2D animation style',
                    'Basic color harmony',
                    'Professional scriptwriting & voice-over',
                    'HD (MP4) – 16:9 aspect ratio (60 FPS)',
                    'Unlimited graphical revisions',
                    '7 days delivery',
                    '7 days of post-delivery support',
                ],
                'is_popular' => false,
                'position' => 1,
                'icon_images' => null,
                'status' => 'active',
            ],
            [
                'title' => 'Momentum Plan',
                'subtitle' => null,
                'description' => 'Brands looking to elevate their presence with stronger visuals.',
                'price' => 399.99,
                'explore_link' => 'https://www.youtube.com/playlist?list=PLyoobzILxORHwuLpv30EQIm0tTNq0EqNP',
                'specification_text' => null,
                'features' => [
                    'Brand-focused “Style 1” animation',
                    'Full brand color, font & logo integration',
                    'Professional scriptwriting & voice-over (unlimited re-recordings)',
                    'Enhanced motion graphics',
                    'Social media formats (16:9, 9:16, 1:1) – 60 FPS',
                    'Unlimited script, voice & graphical revisions',
                    '10 days delivery',
                    '14 days of priority support',
                ],
                'is_popular' => true,
                'position' => 2,
                'icon_images' => null,
                'status' => 'active',
            ],
            [
                'title' => 'Signature Series',
                'subtitle' => null,
                'description' => 'Premium clients seeking a cinematic, fully tailor-made experience.',
                'price' => 599.99,
                'explore_link' => 'https://www.youtube.com/playlist?list=PLyoobzILxORH7EO_VdHRnTWMBwFTosLiQ',
                'specification_text' => null,
                'features' => [
                    'Fully custom 2D animation (built from scratch)',
                    'Entire visual language designed for your brand',
                    'Storyboarding + hand-crafted custom illustrations',
                    'Advanced cinematic-grade motion design',
                    'HD or 4K (YouTube + social formats 16:9, 9:16, 1:1) – 60 FPS',
                    'Unlimited full-scope revisions (script + visual + voice)',
                    'Project-based delivery timeline',
                    '1 month of dedicated post-delivery support',
                    'Unlimited creative consultations',
                ],
                'is_popular' => false,
                'position' => 3,
                'icon_images' => null,
                'status' => 'active',
            ],
        ];

        foreach ($packages as $data) {
            Package::updateOrCreate(
                ['title' => $data['title']],
                $data
            );
        }
    }
}


