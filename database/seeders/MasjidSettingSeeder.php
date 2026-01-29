<?php

namespace Database\Seeders;

use App\Models\MasjidSetting;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class MasjidSettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $settings = [
            'key' => 'masjidProfile',
            'value' => json_encode([
                'nama_masjid' => '', 
                'alamat' => '',
                'telp' => '',
                'logo' => '',
                'kota' => '',
                'timezone' => 'Asia/Jakarta',
                'latitude' => '',
                'longitude' => ''
            ])
        ];
        DB::table('masjid_settings')->updateOrInsert(['key' => $settings['key']], $settings);
    }
}
