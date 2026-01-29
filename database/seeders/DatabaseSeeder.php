<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Database\Seeders\CitySeeder;
use Database\Seeders\SettingSeeder;
use Database\Seeders\WallpaperSeeder;
use Database\Seeders\MasjidSettingSeeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::create([
            'name' => 'Admin Masjid',
            'email' => 'admin@masjid.com',
            'password' => bcrypt('password'),
        ]);

        $this->call([
            SettingSeeder::class,
            CitySeeder::class,
            MasjidSettingSeeder::class,
            WallpaperSeeder::class
        ]);
    }
}
