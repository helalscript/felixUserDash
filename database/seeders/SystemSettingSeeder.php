<?php

namespace Database\Seeders;

use App\Models\SystemSetting;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class SystemSettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        SystemSetting::create([
            'title' => 'Felix',
            'system_name' => 'Felix',
            'email' => 'example@email.com',
            'contact_number' => '+880000000',
            'company_open_hour' => '24 HOUR',
            'copyright_text' => '© Copyright 2026, All right reserved',
            'logo' => 'uploads/logos/logo.png',
            'favicon' => 'uploads/favicons/favicon.png',
            'footer_logo' => 'uploads/footer_logo/footer_logo.png',
            'address' => 'TEST',
            'description' => 'TEST',
        ]);
    }
}
