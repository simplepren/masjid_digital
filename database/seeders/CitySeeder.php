<?php

namespace Database\Seeders;

use App\Models\City;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Http;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class CitySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Gunakan config() sebagai best practice, atau pastikan .env terbaca
        $baseUrl = 'https://api.myquran.com/v2/sholat/kota/semua'; 

        $response = Http::get($baseUrl);

        if ($response->successful()) {
            $json = $response->json();
            $data = $json['data'] ?? [];

            foreach ($data as $item) {
                City::updateOrCreate(
                    ['lokasi_id' => $item['id']],
                    ['lokasi' => $item['lokasi']]
                );
            }
        }else{
            \Log::error("City Seeder Error: Unable to fetch data from API.");
        }
    }
}
