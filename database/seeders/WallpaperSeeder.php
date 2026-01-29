<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class WallpaperSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $wallpapers = [
            [
                'key' => 'wallpaper_images',
                'value' => json_encode([
                    'images' => json_encode(["default.jpg"])
                ]),
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'key' => 'wallpaper_durasi',
                'value' => json_encode([
                    'durasi' => 900
                ]),
                'created_at' => now(),
                'updated_at' => now()
            ],
        ];

        DB::table('wallpapers')->insert($wallpapers);
    }
}
