<?php

namespace App\Models;

use Illuminate\Support\Facades\Http;
use Illuminate\Database\Eloquent\Model;

class PrayerSchedule extends Model
{
    protected $fillable = [
        'date',
        'imsak',
        'subuh',
        'terbit',
        'dhuha',
        'dzuhur',
        'ashar',
        'maghrib',
        'isya',
    ];
    
    public static function syncFromApi($city, $year, $month)
    {
        // Gunakan config() sebagai best practice, atau pastikan .env terbaca
        $baseUrl = env('API_JADWAL_SHOLAT'); 
        $endpoint = "{$baseUrl}/{$city}/{$year}/{$month}";
        
        try {
            $response = Http::get($endpoint);

            if ($response->successful()) {
                $json = $response->json();
                
                // PERBAIKAN: Akses index 'jadwal' di dalam 'data'
                $dataJadwal = $json['data']['jadwal'] ?? []; 

                foreach ($dataJadwal as $item) {
                    self::updateOrCreate(
                        ['date' => $item['date']], // 'date' sudah sesuai (2026-01-01)
                        [
                            'imsak'   => $item['imsak'],
                            'subuh'   => $item['subuh'],
                            'terbit'  => $item['terbit'],
                            'dhuha'   => $item['dhuha'],
                            'dzuhur'  => $item['dzuhur'],
                            'ashar'   => $item['ashar'],
                            'maghrib' => $item['maghrib'],
                            'isya'    => $item['isya'],
                        ]
                    );
                }
                return true;
            }
        } catch (\Exception $e) {
            \Log::error("Sync Error: " . $e->getMessage());
        }
        return false;
    }
}
