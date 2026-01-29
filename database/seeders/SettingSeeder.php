<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SettingSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            [
                'key' => 'durasi_adzan',
                'value' => json_encode([
                    'subuh' => 180, 'dzuhur' => 180, 'ashar' => 180, 'maghrib' => 180, 'isya' => 180
                ]),
            ],
            [
                'key' => 'durasi_iqomah',
                'value' => json_encode([
                    'subuh' => 600, 'dzuhur' => 600, 'ashar' => 300, 'maghrib' => 600, 'isya' => 600
                ]),
            ],
            [
                'key' => 'durasi_sholat',
                'value' => json_encode([
                    'subuh' => 900, 'dzuhur' => 900, 'ashar' => 900, 'maghrib' => 900, 'isya' => 900, 'jumat' => 2400
                ]),
            ],
            [
                'key' => 'corrections',
                'value' => json_encode([
                    'subuh' => 0, 'dzuhur' => 0, 'ashar' => 0, 'maghrib' => 0, 'isya' => 0
                ]),
            ],
            [
                'key' => 'hijri_offset',
                'value' => json_encode([
                    'offset' => 0
                ]),
            ],
            [
                'key' => 'display_template',
                'value' => json_encode([
                    'default' => 'template-one'
                ])
            ]
        ];

        foreach ($settings as $setting) {
            DB::table('settings')->updateOrInsert(['key' => $setting['key']], $setting);
        }
    }
}