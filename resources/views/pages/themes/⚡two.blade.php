<?php

use Carbon\Carbon;
use Livewire\Component;
use Livewire\Attributes\On;
use App\Models\PrayerSchedule;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\DB;

new #[Layout('layouts::display')]
class extends Component
{
    //profil masjid
    public $nama_masjid;
    public $alamat;
    public $telp;
    public $logo;

    //wallpaper
    public $wallpaperImages = [];
    public $wallpaperDurasi = [];

    //prayertimes
    public array $prayerTimes = [];
    public array $durasiAdzan = [];
    public array $durasiIqomah = [];
    public array $durasiSholat = [];
    public array $runningTexts = [];
    public int $hijriOffset = 0;
    public string $currentDate;
    public array $textSlider = [];

    public function mount()
    {
        $this->currentDate = now()->format('Y-m-d');
        $this->getWallpaperImages();
        $this->getMasjidProfile();
        $this->getPrayerTimes();
        $this->autoSync();
        $this->getRunningTexts();
        $this->getDurasiSholat();
        $this->getHijriOffset();
        $this->getTextSlider();
    }

    public function getMasjidProfile()
    {
        $profile = json_decode(DB::table('masjid_settings')->where('key', 'masjidProfile')->value('value'), true) ?? [];
        $this->nama_masjid = $profile['nama_masjid'] ?: 'Nama Masjid';
        $this->alamat      = $profile['alamat']      ?: 'Alamat Masjid';
        $this->telp        = $profile['telp']        ?? '';
        $this->logo        = $profile['logo']        ?? '';
    }

    #[On('echo:display-updated,.DisplayUpdates')]
    public function updateMasjidProfile($payload)
    {
        $type = $payload['type'] ?? null;
        $data = $payload['data'] ?? null;

        // logger($type . '...' . json_encode($data));
        match ($type) {
            'profileMasjidUpdated' => $this->getMasjidProfile(),
            'wallpaperUpdated'     => $this->getWallpaperImages(),
            'runningTextUpdated'   => $this->getRunningTexts(),
            'correctionUpdated'    => $this->handlePrayerRelatedUpdate(),
            'prayerSettingUpdated' => $this->handlePrayerRelatedUpdate(),
            'hijriUpdated'         => $this->handleHijriUpdate(),
            'textSliderUpdated'    => $this->getTextSlider(),
            default                => null,
        };
    }

    #[On('refresh-schedule')]
    public function refreshScheduleIfNeeded()
    {
        $today = now()->format('Y-m-d');

        if ($this->currentDate !== $today) {
            $this->currentDate = $today;
            $this->autoSync();     // autoSynchronize jadwal sholat utk tanggal 25 ke atas
            $this->getPrayerTimes();
            $this->dispatchPrayerUpdate();
            logger('Schedule Refreshed pada tanggal: ' . $today);
        }
    }

    protected function dispatchPrayerUpdate(): void
    {
        $this->dispatch('prayers-updated',
            prayers: $this->prayerTimes,
            offset: $this->hijriOffset,
            settings: [
                'durasiAdzan'   => $this->durasiAdzan,
                'durasiIqomah'  => $this->durasiIqomah,
                'durasiSholat'  => $this->durasiSholat,
                'hijriOffset'   => $this->hijriOffset,
            ]
        );
    }

    protected function handlePrayerRelatedUpdate(): void
    {
        $this->getPrayerTimes();
        $this->getDurasiSholat();
        $this->dispatchPrayerUpdate();
    }

    protected function handleHijriUpdate(): void
    {
        $this->getHijriOffset();
        $this->dispatchPrayerUpdate();
    }

    public function getWallpaperImages()
    {
        $wallpaperImages = DB::table('wallpapers')->where('key', 'wallpaper_images')->first();
        $wallpaperDurasi = DB::table('wallpapers')->where('key', 'wallpaper_durasi')->first();
        $this->wallpaperImages = $wallpaperImages ? json_decode($wallpaperImages->value, true) : [];
        $this->wallpaperDurasi = $wallpaperDurasi ? json_decode($wallpaperDurasi->value, true) : [];
        $this->dispatch('wallpaper-updated', 
            images: $this->wallpaperImages, 
            durasi: $this->wallpaperDurasi
        );
    }

    public function getPrayerTimes()
    {
        // $todayDate = now()->format('Y-m-d');
        $schedule = PrayerSchedule::where('date', $this->currentDate)->first();

        // 1. Jika data tidak ada, lakukan sinkronisasi
        if (!$schedule) {
            $masjid_setting = DB::table('masjid_settings')->where('key', 'masjidProfile')->first();
            if ($masjid_setting) {
                $dt_masjid_profile = json_decode($masjid_setting->value, true);
                $kota = $dt_masjid_profile['kota'] ?? '';

                if ($kota != '') {
                    // Jalankan sinkronisasi
                    PrayerSchedule::syncFromApi($kota, now()->year, now()->month);
                    
                    // AMBIL ULANG data schedule setelah sync selesai agar tidak perlu refresh browser
                    $schedule = PrayerSchedule::where('date', $this->currentDate)->first();
                }
            }
        }

        // 2. Load Corrections
        $dt_corrections = DB::table('settings')->where('key', 'corrections')->first();
        $corrections = $dt_corrections ? json_decode($dt_corrections->value, true) : [];

        // 3. Helper function untuk koreksi waktu
        $adjustTime = function($timeString, $key) use ($corrections) {
            if (!$timeString || $timeString == '00:00') return '00:00';
            $minutes = (int) ($corrections[$key] ?? 0);
            try {
                return Carbon::createFromFormat('H:i', $timeString)
                    ->addMinutes($minutes)
                    ->format('H:i');
            } catch (\Exception $e) {
                return $timeString;
            }
        };

        // 4. Mapping data (Jika $schedule masih null setelah sync, return default 00:00)
        $this->prayerTimes = [
            ['key' => 'subuh',   'label' => 'Subuh',   'time' => $schedule ? $adjustTime($schedule->subuh, 'subuh') : '00:00', 'hasAdzan' => true],
            ['key' => 'terbit',  'label' => 'Syuruq',  'time' => $schedule ? $adjustTime($schedule->terbit, 'terbit') : '00:00', 'hasAdzan' => false],
            ['key' => 'dhuha',   'label' => 'Dhuha',   'time' => $schedule ? $adjustTime($schedule->dhuha, 'dhuha') : '00:00', 'hasAdzan' => false],
            ['key' => 'dzuhur',  'label' => 'Dzuhur',  'time' => $schedule ? $adjustTime($schedule->dzuhur, 'dzuhur') : '00:00', 'hasAdzan' => true],
            ['key' => 'ashar',   'label' => 'Ashar',   'time' => $schedule ? $adjustTime($schedule->ashar, 'ashar') : '00:00', 'hasAdzan' => true],
            ['key' => 'maghrib', 'label' => 'Maghrib', 'time' => $schedule ? $adjustTime($schedule->maghrib, 'maghrib') : '00:00', 'hasAdzan' => true],
            ['key' => 'isya',    'label' => 'Isya',    'time' => $schedule ? $adjustTime($schedule->isya, 'isya') : '00:00', 'hasAdzan' => true],
        ];
    }

    public function getDurasiSholat()
    {
        $settings = DB::table('settings')
            ->whereIn('key', ['durasi_adzan', 'durasi_iqomah', 'durasi_sholat'])
            ->get()
            ->pluck('value', 'key'); // Hasil: ['durasi_adzan' => '{...}', 'durasi_iqomah' => '{...}']

        // 2. Map ke property dengan fallback nilai default jika data di DB kosong
        $this->durasiAdzan = isset($settings['durasi_adzan']) 
            ? json_decode($settings['durasi_adzan'], true) 
            : ['subuh' => 180, 'dzuhur' => 180, 'ashar' => 180, 'maghrib' => 180, 'isya' => 180];

        $this->durasiIqomah = isset($settings['durasi_iqomah']) 
            ? json_decode($settings['durasi_iqomah'], true) 
            : ['subuh' => 600, 'dzuhur' => 600, 'ashar' => 300, 'maghrib' => 600, 'isya' => 600];

        $this->durasiSholat = isset($settings['durasi_sholat']) 
            ? json_decode($settings['durasi_sholat'], true) 
            : ['subuh' => 900, 'dzuhur' => 900, 'ashar' => 900, 'maghrib' => 900, 'isya' => 900, 'jumat' => 2400];
    }

    public function getHijriOffset() 
    {
        $offset = DB::table('settings')->where('key', 'hijri_offset')->first();
        $dt_offset = $offset ? json_decode($offset->value, true) : [];
        $this->hijriOffset = $dt_offset['offset'] ?? 0;
    }

    public function autoSync()
    {
        $now = now();
        
        // 1. Hanya cek sinkronisasi jika sudah akhir bulan (misal > 25)
        if ($now->day > 25) {
            $nextMonth = $now->copy()->addMonth();
            $targetYear = $nextMonth->year;
            $targetMonth = $nextMonth->month;

            // 2. CEK DULU: Apakah jadwal bulan depan sudah ada di database?
            $exists = PrayerSchedule::where('date', $nextMonth->startOfMonth()->format('Y-m-d'))->exists();

            if (!$exists) {
                $masjid_setting = DB::table('masjid_settings')->where('key', 'masjidProfile')->first();
                
                if ($masjid_setting) {
                    $dt_masjid_profile = json_decode($masjid_setting->value, true);
                    $kota = $dt_masjid_profile['kota'] ?? '';
                    if ($kota != '') {
                        PrayerSchedule::syncFromApi($kota, $targetYear, $targetMonth);
                    }
                }
            }
        }
    }

    public function getRunningTexts()
    {
        $texts = DB::table('running_texts')
            ->where('active', true)
            ->orderBy('order_index')
            ->pluck('text')
            ->toArray();
        
        $this->runningTexts = $texts;
    }


    public function getTextSlider()
    {
        $this->textSlider = DB::table('text_sliders')
            ->where('active', true)
            ->orderBy('order_index')
            ->pluck('text')
            ->toArray();
    }

};

?>

{{-- Blade --}}
<div x-data="masjidApp({
        prayerTimes: @js($prayerTimes),
        durasiAdzan: @js($durasiAdzan),
        durasiIqomah: @js($durasiIqomah),
        durasiSholat: @js($durasiSholat),
        hijriOffset: @js($hijriOffset),
        textSlider: @js($textSlider),
    })"
    x-init="init()" 
    x-on:prayers-updated="applyPrayerUpdate($event.detail)"
    class="h-screen w-screen overflow-hidden relative" 
    x-cloak 
    >
    <div 
        x-data="wallpaperRotator({
            images: @js($wallpaperImages['images'] ?? []),
            duration: {{ $wallpaperDurasi['durasi'] ?? 10 }} 
        })"
        class="absolute inset-0 overflow-hidden bg-black"
        x-on:wallpaper-updated="updateImages($event.detail.images); updateDuration($event.detail.durasi)"
        x-cloak
    >
        <div 
            class="absolute inset-0 bg-cover bg-center"
            :style="`background-image: url('/assets/images/wallpaper/${previous}')`"
        ></div>
        <div 
            class="absolute inset-0 bg-cover bg-center transition-opacity duration-2000 ease-in-out"
            :class="isTransitioning ? 'opacity-0' : 'opacity-100'"
            :style="`background-image: url('/assets/images/wallpaper/${current}')`"
        ></div>
    </div>
    <div class="absolute inset-0 bg-black/20"></div>
    <div class="absolute top-0 inset-x-0 h-24 bg-gray-900/90 text-white px-8 flex items-center justify-end z-10">
        <div class="text-4xl flex items-center gap-3">
            <div class="text-green-400 animate-pulse"><flux:icon.dot /></div>
            <span class="font-semibold text-teal-100" x-text="hari"></span>
            <span x-text="tanggalMasehi"></span>
            <span class="text-teal-500">/</span>
            <span class="text-yellow-200" x-text="hijri"></span>
        </div>
    </div>
    <div class="absolute top-0 left-0 w-6/12 bg-linear-to-r from-teal-800 via-teal-700 to-teal-500 h-44 rounded-br-[5rem] flex items-center gap-6 p-6 shadow-2xl z-20">
        <div class="shrink-0 px-6">
            @if($logo)
                <img src="{{ asset('assets/images/'.$logo) }}" class="w-32 h-32 object-contain" alt="Logo">
            @else
                <span class="text-2xl text-white">Logo</span>
            @endif
        </div>
        <div class="flex flex-col space-y-2">
            <h1 class="text-white text-5xl font-bold tracking-tight">{{ $nama_masjid }}</h1>
            <p class="text-teal-50 text-xl opacity-90 line-clamp-1">{{ $alamat }}</p>
            <p class="text-teal-50 text-xl opacity-90 line-clamp-1">Telp. {{ $telp }}</p>
        </div>
    </div>

    <div class="fixed top-50 left-8 opacity-20 hover:opacity-100 transition-opacity duration-500">
        <button 
            @click="toggleMute()" 
            class="p-4 rounded-full shadow-lg transition-all duration-300 transform hover:scale-110 active:scale-95"
            :class="isMuted ? 'bg-red-600' : 'bg-gray-400'"
        >
            <template x-if="!isMuted">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.536 8.464a5 5 0 010 7.072m2.828-9.9a9 9 0 010 12.728M5.586 15H4a1 1 0 01-1-1v-4a1 1 0 011-1h1.586l4.707-4.707C10.923 3.663 12 4.109 12 5v14c0 .891-1.077 1.337-1.707.707L5.586 15z" />
                </svg>
            </template>
            
            <template x-if="isMuted">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5.586 15H4a1 1 0 01-1-1v-4a1 1 0 011-1h1.586l4.707-4.707C10.923 3.663 12 4.109 12 5v14c0 .891-1.077 1.337-1.707.707L5.586 15z" />
                    <path stroke-linecap="round" stroke-linejoin="round" d="M17 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2" />
                </svg>
            </template>
        </button>
    </div>

    <div 
        x-data
        class="absolute inset-0 z-20 flex items-center justify-center overflow-hidden"
    >
        <template x-for="(text, index) in textSlider" :key="index">
            <div
                x-show="activeSlide === index"
                x-transition:enter="transition transform duration-700 ease-in-out"
                x-transition:enter-start="translate-x-full opacity-0"
                x-transition:enter-end="translate-x-0 opacity-100"
                x-transition:leave="transition transform duration-700 ease-in-out absolute"
                x-transition:leave-start="translate-x-0 opacity-100"
                x-transition:leave-end="-translate-x-full opacity-0"
                class="w-full flex justify-center"
            >
                <div class="text-gray-900 text-center bg-white/90 backdrop-blur-md rounded-2xl p-6 text-4xl max-w-[75%]">
                    <span x-text="text"></span>
                </div>
            </div>
        </template>
    </div>


    <div class="absolute top-36 right-50 h-18 flex items-center w-64 z-20">
        <div class="bg-teal-700 p-3 rounded-l-xl shadow-lg">
            <flux:icon.clock class="w-12 h-12 text-white" />
        </div>
        <div class="bg-white/95 backdrop-blur flex items-center gap-4 px-6 h-18 rounded-r-xl text-3xl shadow-lg border-y-2 border-r-2 border-teal-600">
            <span class="text-gray-600 font-medium" x-text="displayLabel(getNextPrayerObject())"></span>
            <span class="font-bold text-teal-700 tabular-nums" x-text="countdown"></span>
        </div>
    </div>

    <div class="absolute bottom-22 right-0 left-68 h-36 bg-teal-900/80 backdrop-blur-md grid grid-cols-7 gap-0.5 p-0.5 shadow-2xl z-10">
        <template x-for="(p, i) in prayers" :key="p.key">
            <div class="flex flex-col items-center justify-center transition-all duration-500 space-y-2" 
                :class="currentIndex() === i ? 'bg-yellow-700 border-2 border-yellow-500 text-white scale-105 z-20 shadow-lg' : 'bg-teal-700 text-white'">
                <span class="text-3xl font-medium" x-text="displayLabel(p)"></span>
                <span class="text-5xl font-bold" x-text="p.time"></span>
                <template x-if="currentIndex() === i">
                    <span class="text-[15px] uppercase tracking-widest font-black text-yellow-200 mt-1">Sekarang</span>
                </template>
            </div>
        </template>
    </div>

    <div class="absolute bottom-8 left-8 z-50 transform scale-110 w-60 h-60 flex items-center justify-center">
        <div x-show="showAnalog" 
            x-cloak
            x-transition:enter="transition ease-out duration-1000"
            x-transition:enter-start="opacity-0 scale-60"
            x-transition:enter-end="opacity-100 scale-100"
            x-transition:leave="transition ease-in duration-1000"
            x-transition:leave-start="opacity-100 scale-100"
            x-transition:leave-end="opacity-0 scale-60"
            class="absolute"> <livewire:analog-clock />
        </div>
        <div x-show="!showAnalog" 
            x-cloak
            x-transition:enter="transition ease-out duration-1000"
            x-transition:enter-start="opacity-0 scale-60"
            x-transition:enter-end="opacity-100 scale-100"
            x-transition:leave="transition ease-in duration-1000"
            x-transition:leave-start="opacity-100 scale-100"
            x-transition:leave-end="opacity-0 scale-60"
            class="absolute w-68 h-68 rounded-full bg-teal-600 flex justify-center items-center shadow-2xl">
            <div class="bg-white w-64 h-64 rounded-full flex flex-col justify-center items-center shadow-lg">
                <div class="text-[82px] font-bold text-teal-700 tabular-nums" x-text="time"></div>
            </div>
        </div>
    </div>

    <div class="absolute bottom-0 right-0 left-32 h-22 bg-black/90 flex items-center overflow-hidden border-t border-teal-500/30 z-10">
        <div class="marquee-container w-full relative h-full flex items-center">
            <div class="marquee-content shrink-0 flex items-center">
                @foreach($runningTexts as $text)
                    <span class="text-white text-4xl px-12 flex items-center whitespace-nowrap">
                        <span class="text-yellow-500 mr-3">✦</span> 
                        {{ $text }}
                    </span>
                @endforeach
                @foreach($runningTexts as $text)
                    <span class="text-white text-4xl px-12 flex items-center whitespace-nowrap">
                        <span class="text-yellow-500 mr-3">✦</span> 
                        {{ $text }}
                    </span>
                @endforeach
                {{-- @foreach($runningTexts as $text)
                    <span class="text-white text-4xl px-12 flex items-center whitespace-nowrap">
                        <span class="text-yellow-500 mr-3">✦</span> 
                        {{ $text }}
                    </span>
                @endforeach --}}
            </div>
        </div>
    </div>

    {{-- Overlay Mode Adzan dan Iqomah --}}
    <div x-show="mode === 'ADZAN' || mode === 'IQOMAH'" x-cloak 
        x-transition:enter="transition duration-[1500ms] ease-out"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition duration-[500ms] ease-in"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="absolute inset-0 z-50 flex items-center justify-center shadow-inner" 
        style="background-color: #f7e2bc">
        
        <div class="text-center w-full">
            <div class="relative h-24 mb-10 flex items-center justify-center">
                <div x-show="mode === 'ADZAN'"
                    x-transition:enter="transition duration-[800ms] ease-out"
                    x-transition:enter-start="opacity-0 blur-xl scale-90"
                    x-transition:enter-end="opacity-100 blur-0 scale-100"
                    x-transition:leave="transition duration-[500ms] ease-in absolute"
                    x-transition:leave-start="opacity-100 blur-0"
                    x-transition:leave-end="opacity-0 blur-lg"
                    class="text-7xl font-bold" style="color: #927a38">
                    SAAT ADZAN <span x-text="displayLabel(prayers[nextIndex]).toUpperCase()"></span>
                </div>

                <div x-show="mode === 'IQOMAH'"
                    x-transition:enter="transition duration-[800ms] ease-out delay-500"
                    x-transition:enter-start="opacity-0 blur-xl scale-90"
                    x-transition:enter-end="opacity-100 blur-0 scale-100"
                    x-transition:leave="transition duration-[500ms] ease-in absolute"
                    x-transition:leave-start="opacity-100 blur-0"
                    x-transition:leave-end="opacity-0 blur-lg"
                    class="text-7xl font-bold" style="color: #927a38">
                    MENJELANG IQOMAH <span x-text="displayLabel(prayers[nextIndex]).toUpperCase()"></span>
                </div>
            </div>
            <div class="flex justify-center mb-12 shadow-2xl">
                <template x-if="mode === 'ADZAN' || mode === 'IQOMAH'">
                    <div class="flex items-center justify-center gap-3 font-mono tabular-nums select-none">
                        <template x-for="(char, index) in phaseCountdown.split('')" :key="`${mode}-${index}`">
                            <span
                                x-text="char"
                                class="inline-flex items-center justify-center font-black leading-none"
                                :class="char === ':'
                                    ? 'w-10 text-[8rem]'
                                    : 'w-28 h-40 rounded-2xl bg-white/80 border-4 border-[#927a38]/30 shadow-2xl text-[9rem]'"
                                style="color:#927a38"
                            ></span>
                        </template>
                    </div>
                </template>
            </div>
            <div class="relative h-20 mt-10 flex items-center justify-center">
                <div :key="mode" 
                    class="pt-4 pb-5 px-12 bg-white border-8 border-yellow-500 rounded-full text-3xl font-bold shadow-lg transition-all duration-1000"
                    :class="mode === 'IQOMAH' ? 'scale-105 border-yellow-600' : ''">
                    <span x-text="mode === 'ADZAN' ? 'Luruskan Niat, Bersihkan Hati' : 'Rapatkan Shaf Untuk Kesempurnaan Sholat Berjamaah'"></span>
                </div>
            </div>
        </div>
    </div>

    {{-- Overlay Mode Sholat --}}
    <div x-show="mode === 'SHOLAT'" x-cloak 
        x-transition:enter="transition duration-200"
        x-transition:enter-start="opacity-50"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition duration-1000"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="absolute inset-0 z-50 bg-black flex items-center justify-center">
        
        <div class="text-center w-1/2"> 
            <div class="text-white text-3xl font-bold mb-3 tracking-widest opacity-50">
                <span>WAKTU SHOLAT</span>
                <span x-text="currentPrayerName"></span>
            </div>

            {{-- Progress Bar Container --}}
            <div class="h-2 rounded-full bg-black overflow-hidden w-full border border-gray-700">
                {{-- Bagian yang bergerak (Progress) --}}
                <div class="h-full bg-yellow-700 transition-all duration-1000 ease-linear"
                    :style="`width: ${ totalSholatDuration > 0 ? (sholatRemaining / totalSholatDuration) * 100 : 0 }%`"
                ></div>
            </div>
        </div>
    </div>

</div>


@script
<script>
    Alpine.data('wallpaperRotator', ({ images, duration }) => ({
        images: images,
        duration: duration * 1000,
        index: 0,
        current: '',
        previous: '',
        isTransitioning: false,
        timer: null,

        init() {
            if (!this.images || !this.images.length) return;
            
            this.current = this.images[0];
            this.previous = this.images[0];
            this.startTimer();
        },

        startTimer() {
            this.stopTimer();
            this.timer = setInterval(() => this.next(), this.duration);
        },

        stopTimer() {
            if (this.timer) clearInterval(this.timer);
        },

        async next() {
            if (this.images.length <= 1) return;
            this.previous = this.current;
            this.isTransitioning = true;

            // 3. Tunggu sebentar (durasi transisi CSS), lalu ganti gambar depan
            setTimeout(() => {
                this.index = (this.index + 1) % this.images.length;
                this.current = this.images[this.index];
                
                // 4. Munculkan kembali layer depan dengan gambar baru
                this.isTransitioning = false;
            }, 1000);
        },

        updateImages(newImages) {
            this.images = [...newImages];
            this.index = 0;
            this.current = this.images[0];
        },

        updateDuration(newDuration) {
            this.duration = Number(newDuration) * 1000;
            this.startTimer();
        },

        destroy() {
            this.stopTimer();
        }
    }));

    Alpine.data('masjidApp', ({ prayerTimes, durasiAdzan, durasiIqomah, durasiSholat, hijriOffset, textSlider }) => ({
        // =============================================================
        // UI STATE
        // =============================================================
        prayers: prayerTimes ?? [],
        mode: 'COUNTDOWN', // COUNTDOWN | ADZAN | IQOMAH | SHOLAT
        nextIndex: 0,
        activePrayerKey: null,
        activePrayerDate: null,

        time: '',
        hari: '',
        tanggalMasehi: '',
        hijri: '',
        baseHijriDate: null,
        hijriOffset: Number(hijriOffset ?? 0),
        countdown: '',
        phaseCountdown: '00:00',
        currentPrayerName: '',

        isMuted: localStorage.getItem('audio_muted') === 'true',
        showAnalog: true,
        isClockTransitioning: false,

        // Slider
        textSlider: textSlider ?? [],
        activeSlide: 0,
        sliderTimer: null,
        sliderInterval: 12000,

        // Prayer settings
        durasiAdzan: durasiAdzan ?? {},
        durasiIqomah: durasiIqomah ?? {},
        durasiSholat: durasiSholat ?? {},

        // Derived timer values consumed by Blade
        adzanRemaining: 0,
        iqomahRemaining: 0,
        sholatRemaining: 0,
        totalSholatDuration: 1,

        // =============================================================
        // ABSOLUTE-TIME ENGINE
        // =============================================================
        masterTimer: null,
        dateTimer: null,
        clockSwitchTimer: null,
        reloadTimer: null,

        countdownTargetAt: null,
        countdownTargetIndex: null,
        phaseStartedAt: null,
        phaseEndsAt: null,
        phaseDurationSeconds: 0,
        transitionLock: false,

        beepAudio1: null,
        beepAudio2: null,
        todayKey: new Date().toDateString(),

        STORAGE_KEY: 'masjid_state_v2',
        STORAGE_VERSION: 2,

        init() {
            this.beepAudio1 = new Audio("{{ asset('assets/audio/beep-01.mp3') }}");
            this.beepAudio2 = new Audio("{{ asset('assets/audio/beep-02.mp3') }}");

            this.updateClock();
            this.updateDate();
            this.initTextSlider();
            this.loopClockSwitch();
            this.setupDailyReload();

            // Restore deadline state. Bila tidak ada/invalid, rekonstruksi keadaan
            // dari jadwal hari ini agar reload/sleep di tengah fase tetap aman.
            if (!this.restoreState()) {
                this.reconcileFromSchedule(Date.now());
            }

            this.startMasterClock();
        },

        destroy() {
            this.stopMasterClock();
            this.stopTextSlider();

            if (this.dateTimer) clearInterval(this.dateTimer);
            if (this.clockSwitchTimer) clearInterval(this.clockSwitchTimer);
            if (this.reloadTimer) clearTimeout(this.reloadTimer);

            this.dateTimer = null;
            this.clockSwitchTimer = null;
            this.reloadTimer = null;
        },

        // =============================================================
        // MASTER HEARTBEAT
        // setInterval hanya heartbeat. Sumber kebenaran waktu selalu Date.now().
        // =============================================================
        startMasterClock() {
            this.stopMasterClock();

            this.masterTimer = setInterval(() => {
                this.tick(Date.now());
            }, 250);

            this.dateTimer = setInterval(() => this.updateDate(), 60000);
        },

        stopMasterClock() {
            if (this.masterTimer) clearInterval(this.masterTimer);
            this.masterTimer = null;
        },

        tick(nowMs = Date.now()) {
            this.updateClock();
            this.checkNewDay();

            if (this.transitionLock) return;

            if (this.mode === 'COUNTDOWN') {
                this.tickCountdown(nowMs);
                return;
            }

            this.tickActivePhase(nowMs);
        },

        tickActivePhase(nowMs) {
            if (!this.phaseEndsAt) {
                this.reconcileFromSchedule(nowMs);
                return;
            }

            const remaining = Math.max(0, Math.ceil((this.phaseEndsAt - nowMs) / 1000));
            this.setPhaseRemaining(remaining);

            if (nowMs >= this.phaseEndsAt) {
                this.advancePhase(nowMs);
            }
        },

        setPhaseRemaining(seconds) {
            const safe = Math.max(0, Number(seconds) || 0);
            this.phaseCountdown = this.formatPhaseTime(safe);

            this.adzanRemaining = this.mode === 'ADZAN' ? safe : 0;
            this.iqomahRemaining = this.mode === 'IQOMAH' ? safe : 0;
            this.sholatRemaining = this.mode === 'SHOLAT' ? safe : 0;
        },

        formatPhaseTime(totalSeconds) {
            const mins = String(Math.floor(totalSeconds / 60)).padStart(2, '0');
            const secs = String(totalSeconds % 60).padStart(2, '0');
            return `${mins}:${secs}`;
        },

        // =============================================================
        // COUNTDOWN -> PRAYER EVENT
        // Target disimpan sebagai timestamp absolut agar callback terlambat
        // tidak menyebabkan event sholat terlewat.
        // =============================================================
        tickCountdown(nowMs) {
            if (!this.countdownTargetAt || this.countdownTargetIndex === null) {
                this.selectNextCountdownTarget(nowMs);
            }

            if (!this.countdownTargetAt || this.countdownTargetIndex === null) return;

            if (nowMs >= this.countdownTargetAt) {
                const reachedIndex = this.countdownTargetIndex;
                const scheduledAt = this.countdownTargetAt;
                this.handleReachedPrayer(reachedIndex, scheduledAt, nowMs);
                return;
            }

            const diff = Math.max(0, Math.ceil((this.countdownTargetAt - nowMs) / 1000));
            const hrs = String(Math.floor(diff / 3600)).padStart(2, '0');
            const mins = String(Math.floor((diff % 3600) / 60)).padStart(2, '0');
            const secs = String(diff % 60).padStart(2, '0');
            this.countdown = `-${hrs}:${mins}:${secs}`;
        },

        selectNextCountdownTarget(nowMs = Date.now()) {
            const next = this.findNextPrayerOccurrence(nowMs);
            if (!next) {
                this.countdownTargetAt = null;
                this.countdownTargetIndex = null;
                this.countdown = '--:--:--';
                return;
            }

            this.nextIndex = next.index;
            this.countdownTargetIndex = next.index;
            this.countdownTargetAt = next.at;
        },

        findNextPrayerOccurrence(nowMs) {
            if (!Array.isArray(this.prayers) || this.prayers.length === 0) return null;

            const now = new Date(nowMs);
            for (let i = 0; i < this.prayers.length; i++) {
                const at = this.prayerTimestamp(this.prayers[i], now);
                if (at > nowMs) return { index: i, at };
            }

            // Setelah event terakhir hari ini, target berikutnya adalah Subuh besok.
            const tomorrow = new Date(now);
            tomorrow.setDate(tomorrow.getDate() + 1);
            tomorrow.setHours(0, 0, 0, 0);

            return {
                index: 0,
                at: this.prayerTimestamp(this.prayers[0], tomorrow),
            };
        },

        prayerTimestamp(prayer, baseDate = new Date()) {
            if (!prayer?.time) return NaN;
            const [h, m] = prayer.time.split(':').map(Number);
            const dt = new Date(baseDate);
            dt.setHours(h, m, 0, 0);
            return dt.getTime();
        },

        handleReachedPrayer(index, scheduledAt, nowMs) {
            const event = this.prayers[index];
            if (!event) {
                this.selectNextCountdownTarget(nowMs + 1000);
                return;
            }

            // Event seperti Syuruq/Dhuha hanya menjadi marker countdown,
            // tidak masuk ke state ADZAN.
            if (!event.hasAdzan) {
                this.countdownTargetAt = null;
                this.countdownTargetIndex = null;
                this.selectNextCountdownTarget(Math.max(nowMs, scheduledAt + 1000));
                this.tickCountdown(nowMs);
                return;
            }

            this.startAdzan({
                prayerIndex: index,
                startedAt: scheduledAt,
                nowMs,
                playAudio: true,
            });
        },

        // Kompatibilitas untuk pemanggilan lama dari Blade/event lain.
        updateNextIndex() {
            if (this.mode !== 'COUNTDOWN') return;
            this.selectNextCountdownTarget(Date.now());
        },

        getNextPrayerObject() {
            return this.prayers[this.nextIndex] || this.prayers[0];
        },

        currentIndex() {
            if (this.mode !== 'COUNTDOWN' && this.activePrayerKey) {
                return this.prayers.findIndex(p => p.key === this.activePrayerKey);
            }

            if (this.nextIndex === null || !this.prayers.length) return -1;
            if (this.nextIndex === 0) {
                const now = Date.now();
                const subuhToday = this.prayerTimestamp(this.prayers[0], new Date(now));
                return now > subuhToday ? this.prayers.length - 1 : this.prayers.length - 1;
            }
            return this.nextIndex - 1;
        },

        // =============================================================
        // PHASE STATE MACHINE
        // =============================================================
        startAdzan({ prayerIndex = this.nextIndex, startedAt = Date.now(), nowMs = Date.now(), playAudio = true } = {}) {
            const event = this.prayers[prayerIndex];
            if (!event) return this.reset();

            const duration = this.getDuration(this.durasiAdzan, event.key, 120);
            this.enterPhase('ADZAN', event, prayerIndex, startedAt, duration, nowMs);

            if (playAudio) this.playBeep1(1);
        },

        startIqomah({ startedAt = Date.now(), nowMs = Date.now(), prayerIndex = null } = {}) {
            const index = prayerIndex ?? this.getActivePrayerIndex();
            const event = this.prayers[index];
            if (!event) return this.reset();

            const duration = this.getDuration(this.durasiIqomah, event.key, 300);
            this.enterPhase('IQOMAH', event, index, startedAt, duration, nowMs);
        },

        startSholat({ startedAt = Date.now(), nowMs = Date.now(), prayerIndex = null } = {}) {
            const index = prayerIndex ?? this.getActivePrayerIndex();
            const event = this.prayers[index];
            if (!event) return this.reset();

            const key = this.isFriday(new Date(startedAt)) && event.key === 'dzuhur' ? 'jumat' : event.key;
            const duration = this.getDuration(this.durasiSholat, key, key === 'jumat' ? 2400 : 600);

            this.enterPhase('SHOLAT', event, index, startedAt, duration, nowMs);
            this.totalSholatDuration = duration;
            this.currentPrayerName = this.displayLabel(event, new Date(startedAt)).toUpperCase();
        },

        enterPhase(mode, event, prayerIndex, startedAt, durationSeconds, nowMs = Date.now()) {
            this.transitionLock = true;

            this.mode = mode;
            this.activePrayerKey = event.key;
            this.activePrayerDate = this.localDateKey(new Date(startedAt));
            this.nextIndex = prayerIndex;
            this.phaseStartedAt = startedAt;
            this.phaseDurationSeconds = durationSeconds;
            this.phaseEndsAt = startedAt + (durationSeconds * 1000);
            this.countdownTargetAt = null;
            this.countdownTargetIndex = null;

            const remaining = Math.max(0, Math.ceil((this.phaseEndsAt - nowMs) / 1000));
            this.setPhaseRemaining(remaining);
            this.saveState();

            this.transitionLock = false;

            // Jika browser baru bangun dan deadline fase ini ternyata juga sudah lewat,
            // lanjutkan segera tanpa menunggu heartbeat berikutnya.
            if (nowMs >= this.phaseEndsAt) this.advancePhase(nowMs);
        },

        advancePhase(nowMs = Date.now()) {
            if (this.transitionLock) return;
            this.transitionLock = true;

            const event = this.getActivePrayer();
            const index = this.getActivePrayerIndex();
            const endedAt = this.phaseEndsAt ?? nowMs;
            const previousMode = this.mode;

            if (!event || index < 0) {
                this.transitionLock = false;
                this.reset(nowMs);
                return;
            }

            this.transitionLock = false;

            if (previousMode === 'ADZAN') {
                if (this.isFriday(new Date(endedAt)) && event.key === 'dzuhur') {
                    this.startSholat({ startedAt: endedAt, nowMs, prayerIndex: index });
                } else {
                    this.startIqomah({ startedAt: endedAt, nowMs, prayerIndex: index });
                }
                return;
            }

            if (previousMode === 'IQOMAH') {
                this.playBeep2(1);
                this.startSholat({ startedAt: endedAt, nowMs, prayerIndex: index });
                return;
            }

            if (previousMode === 'SHOLAT') {
                this.reset(nowMs);
            }
        },

        getDuration(map, key, fallback) {
            const value = Number(map?.[key]);
            return Number.isFinite(value) && value >= 0 ? value : fallback;
        },

        getActivePrayerIndex() {
            if (!this.activePrayerKey) return this.nextIndex ?? -1;
            return this.prayers.findIndex(p => p.key === this.activePrayerKey);
        },

        getActivePrayer() {
            const index = this.getActivePrayerIndex();
            return index >= 0 ? this.prayers[index] : null;
        },

        reset(nowMs = Date.now()) {
            this.clearState();
            this.mode = 'COUNTDOWN';
            this.activePrayerKey = null;
            this.activePrayerDate = null;
            this.phaseStartedAt = null;
            this.phaseEndsAt = null;
            this.phaseDurationSeconds = 0;
            this.adzanRemaining = 0;
            this.iqomahRemaining = 0;
            this.sholatRemaining = 0;
            this.phaseCountdown = '00:00';
            this.countdownTargetAt = null;
            this.countdownTargetIndex = null;

            this.selectNextCountdownTarget(nowMs + 1);
            this.tickCountdown(nowMs);
        },

        // =============================================================
        // RECONCILIATION
        // Merekonstruksi state dari jadwal absolut. Penting ketika browser
        // direload/dibuka setelah sleep dan localStorage tidak lagi valid.
        // =============================================================
        reconcileFromSchedule(nowMs = Date.now()) {
            if (!Array.isArray(this.prayers) || !this.prayers.length) {
                this.reset(nowMs);
                return;
            }

            const now = new Date(nowMs);
            const candidates = [];

            // Cek hari ini dan kemarin (untuk sholat yang mungkin melewati tengah malam).
            for (const dayOffset of [-1, 0]) {
                const day = new Date(now);
                day.setDate(day.getDate() + dayOffset);
                day.setHours(0, 0, 0, 0);

                this.prayers.forEach((event, index) => {
                    if (!event.hasAdzan) return;

                    const adzanStart = this.prayerTimestamp(event, day);
                    const adzanDuration = this.getDuration(this.durasiAdzan, event.key, 120);
                    const adzanEnd = adzanStart + adzanDuration * 1000;

                    const fridayDzuhur = this.isFriday(new Date(adzanStart)) && event.key === 'dzuhur';
                    const iqomahDuration = fridayDzuhur ? 0 : this.getDuration(this.durasiIqomah, event.key, 300);
                    const iqomahEnd = adzanEnd + iqomahDuration * 1000;

                    const sholatKey = fridayDzuhur ? 'jumat' : event.key;
                    const sholatDuration = this.getDuration(this.durasiSholat, sholatKey, fridayDzuhur ? 2400 : 600);
                    const sholatStart = fridayDzuhur ? adzanEnd : iqomahEnd;
                    const sholatEnd = sholatStart + sholatDuration * 1000;

                    if (nowMs >= adzanStart && nowMs < adzanEnd) {
                        candidates.push({ mode: 'ADZAN', event, index, startedAt: adzanStart, duration: adzanDuration });
                    } else if (!fridayDzuhur && nowMs >= adzanEnd && nowMs < iqomahEnd) {
                        candidates.push({ mode: 'IQOMAH', event, index, startedAt: adzanEnd, duration: iqomahDuration });
                    } else if (nowMs >= sholatStart && nowMs < sholatEnd) {
                        candidates.push({ mode: 'SHOLAT', event, index, startedAt: sholatStart, duration: sholatDuration });
                    }
                });
            }

            if (candidates.length) {
                // Ambil fase yang paling baru mulai.
                candidates.sort((a, b) => b.startedAt - a.startedAt);
                const c = candidates[0];
                this.enterPhase(c.mode, c.event, c.index, c.startedAt, c.duration, nowMs);
                if (c.mode === 'SHOLAT') {
                    this.totalSholatDuration = c.duration;
                    this.currentPrayerName = this.displayLabel(c.event, new Date(c.startedAt)).toUpperCase();
                }
                return;
            }

            this.mode = 'COUNTDOWN';
            this.activePrayerKey = null;
            this.activePrayerDate = null;
            this.phaseStartedAt = null;
            this.phaseEndsAt = null;
            this.selectNextCountdownTarget(nowMs);
            this.tickCountdown(nowMs);
        },

        // =============================================================
        // PERSISTENCE
        // Simpan deadline, bukan remaining counter.
        // =============================================================
        saveState() {
            if (this.mode === 'COUNTDOWN' || !this.activePrayerKey || !this.phaseEndsAt) {
                this.clearState();
                return;
            }

            localStorage.setItem(this.STORAGE_KEY, JSON.stringify({
                version: this.STORAGE_VERSION,
                mode: this.mode,
                prayerKey: this.activePrayerKey,
                prayerDate: this.activePrayerDate,
                phaseStartedAt: this.phaseStartedAt,
                phaseEndsAt: this.phaseEndsAt,
                phaseDurationSeconds: this.phaseDurationSeconds,
            }));
        },

        restoreState() {
            const raw = localStorage.getItem(this.STORAGE_KEY);
            if (!raw) return false;

            try {
                const data = JSON.parse(raw);
                if (data.version !== this.STORAGE_VERSION) {
                    this.clearState();
                    return false;
                }

                const index = this.prayers.findIndex(p => p.key === data.prayerKey);
                if (index < 0 || !['ADZAN', 'IQOMAH', 'SHOLAT'].includes(data.mode)) {
                    this.clearState();
                    return false;
                }

                const nowMs = Date.now();
                if (!Number.isFinite(Number(data.phaseEndsAt)) || nowMs >= Number(data.phaseEndsAt)) {
                    this.clearState();
                    return false;
                }

                const event = this.prayers[index];
                const startedAt = Number(data.phaseStartedAt);
                const duration = Number(data.phaseDurationSeconds) || Math.ceil((Number(data.phaseEndsAt) - startedAt) / 1000);

                this.enterPhase(data.mode, event, index, startedAt, duration, nowMs);
                this.phaseEndsAt = Number(data.phaseEndsAt); // pertahankan deadline persis yang disimpan
                this.activePrayerDate = data.prayerDate ?? this.localDateKey(new Date(startedAt));
                this.setPhaseRemaining(Math.max(0, Math.ceil((this.phaseEndsAt - nowMs) / 1000)));

                if (data.mode === 'SHOLAT') {
                    this.totalSholatDuration = duration;
                    this.currentPrayerName = this.displayLabel(event, new Date(startedAt)).toUpperCase();
                }

                return true;
            } catch (e) {
                console.warn('State masjid tidak valid, melakukan rekonstruksi ulang.', e);
                this.clearState();
                return false;
            }
        },

        clearState() {
            localStorage.removeItem(this.STORAGE_KEY);
            // Hapus state versi lama agar tidak lagi ikut campur.
            localStorage.removeItem('masjid_state');
        },

        getRemaining() {
            if (!this.phaseEndsAt) return 0;
            return Math.max(0, Math.ceil((this.phaseEndsAt - Date.now()) / 1000));
        },

        // =============================================================
        // LIVE UPDATES
        // =============================================================
        applyPrayerUpdate(detail) {
            if (!detail) return;

            if (Array.isArray(detail.prayers)) this.prayers = detail.prayers;

            const settings = detail.settings ?? {};
            if (settings.durasiAdzan) this.durasiAdzan = settings.durasiAdzan;
            if (settings.durasiIqomah) this.durasiIqomah = settings.durasiIqomah;
            if (settings.durasiSholat) this.durasiSholat = settings.durasiSholat;

            if (Object.prototype.hasOwnProperty.call(settings, 'hijriOffset')) {
                this.hijriOffset = Number(settings.hijriOffset);
                this.applyHijriOffset();
            }

            // Satu reconciliation setelah seluruh payload diterapkan, sehingga
            // prayers baru tidak pernah diproses dengan setting durasi lama.
            this.reconcileAfterConfigUpdate();
        },

        updatePrayers(newData) {
            if (!Array.isArray(newData)) return;
            this.prayers = newData;
            this.reconcileAfterConfigUpdate();
        },

        updateSettings(settings) {
            if (!settings) return;
            if (settings.durasiAdzan) this.durasiAdzan = settings.durasiAdzan;
            if (settings.durasiIqomah) this.durasiIqomah = settings.durasiIqomah;
            if (settings.durasiSholat) this.durasiSholat = settings.durasiSholat;

            if (Object.prototype.hasOwnProperty.call(settings, 'hijriOffset')) {
                this.hijriOffset = Number(settings.hijriOffset);
                this.applyHijriOffset();
            }

            this.reconcileAfterConfigUpdate();
        },

        reconcileAfterConfigUpdate() {
            // Saat fase aktif, jangan mengubah deadline yang sudah berjalan karena
            // setting baru seharusnya berlaku pada fase berikutnya.
            if (this.mode === 'COUNTDOWN') {
                this.countdownTargetAt = null;
                this.countdownTargetIndex = null;
                this.selectNextCountdownTarget(Date.now());
                this.tickCountdown(Date.now());
            }
        },

        // =============================================================
        // DATE / HIJRI / LABEL
        // =============================================================
        updateClock() {
            this.time = new Date().toLocaleTimeString('en-GB', {
                hour: '2-digit', minute: '2-digit', hour12: false
            });
        },

        updateDate() {
            const now = new Date();
            const namaHari = ['Ahad', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
            this.hari = namaHari[now.getDay()] + ',';
            this.tanggalMasehi = now.toLocaleDateString('id-ID', {
                day: '2-digit', month: 'long', year: 'numeric'
            });
            this.updateHijriDate(now);
        },

        updateHijriDate(now = new Date()) {
            const baseDate = new Date(now);
            const isAfterMaghrib = localStorage.getItem('hijri_is_after_maghrib') === '1';
            if (isAfterMaghrib) baseDate.setDate(baseDate.getDate() + 1);

            this.baseHijriDate = new Date(baseDate);
            this.applyHijriOffset();
        },

        applyHijriOffset() {
            if (!this.baseHijriDate) return;
            const hijriDate = new Date(this.baseHijriDate);
            hijriDate.setDate(hijriDate.getDate() + this.hijriOffset);
            this.hijri = new Intl.DateTimeFormat('id-ID-u-ca-islamic-umalqura', {
                day: 'numeric', month: 'long', year: 'numeric'
            }).format(hijriDate);
        },

        syncHijriMaghribFlag(nowMs = Date.now()) {
            const maghrib = this.prayers.find(p => p.key === 'maghrib');
            if (!maghrib) return;

            const maghribAt = this.prayerTimestamp(maghrib, new Date(nowMs));
            const isPastMaghrib = nowMs >= maghribAt;
            const currentFlag = localStorage.getItem('hijri_is_after_maghrib');

            if (isPastMaghrib && currentFlag !== '1') {
                localStorage.setItem('hijri_is_after_maghrib', '1');
                this.updateDate();
            } else if (!isPastMaghrib && currentFlag === '1') {
                localStorage.removeItem('hijri_is_after_maghrib');
                this.updateDate();
            }
        },

        displayLabel(prayer, date = new Date()) {
            if (!prayer) return '';
            if (prayer.key === 'dzuhur' && date.getDay() === 5) return "Jum'ah";
            return prayer.label;
        },

        isFriday(date = new Date()) {
            return date.getDay() === 5;
        },

        localDateKey(date = new Date()) {
            const y = date.getFullYear();
            const m = String(date.getMonth() + 1).padStart(2, '0');
            const d = String(date.getDate()).padStart(2, '0');
            return `${y}-${m}-${d}`;
        },

        // =============================================================
        // NEW DAY
        // =============================================================
        checkNewDay() {
            const today = new Date().toDateString();
            if (today === this.todayKey) {
                this.syncHijriMaghribFlag();
                return;
            }

            this.todayKey = today;
            this.onNewDay();
        },

        onNewDay() {
            // Jangan memotong SHOLAT yang sah melewati tengah malam.
            if (this.mode === 'COUNTDOWN') {
                this.clearState();
                this.countdownTargetAt = null;
                this.countdownTargetIndex = null;
            }

            localStorage.removeItem('hijri_is_after_maghrib');
            this.updateDate();

            // Livewire akan mengganti prayerTimes dengan jadwal tanggal baru.
            this.$dispatch('refresh-schedule');
        },

        // =============================================================
        // UI TIMERS (tidak menentukan state ibadah)
        // =============================================================
        loopClockSwitch() {
            if (this.clockSwitchTimer) clearInterval(this.clockSwitchTimer);
            this.clockSwitchTimer = setInterval(() => {
                if (this.isClockTransitioning) return;
                this.isClockTransitioning = true;
                this.showAnalog = !this.showAnalog;
                setTimeout(() => { this.isClockTransitioning = false; }, 1200);
            }, 20000);
        },

        initTextSlider() {
            if (!this.textSlider || this.textSlider.length === 0) return;
            this.stopTextSlider();
            this.sliderTimer = setInterval(() => {
                if (this.mode !== 'COUNTDOWN') return;
                this.nextSlide();
            }, this.sliderInterval);
        },

        nextSlide() {
            this.activeSlide = (this.activeSlide + 1) % this.textSlider.length;
        },

        stopTextSlider() {
            if (this.sliderTimer) clearInterval(this.sliderTimer);
            this.sliderTimer = null;
        },

        // =============================================================
        // AUDIO
        // =============================================================
        playBeep1(times = 1) {
            if (this.isMuted || !this.beepAudio1) return;
            let count = 0;
            const playOnce = () => {
                if (count >= times) return;
                this.beepAudio1.currentTime = 0;
                this.beepAudio1.play().catch(() => console.warn('Audio play blocked'));
                count++;
                if (count < times) setTimeout(playOnce, 500);
            };
            playOnce();
        },

        playBeep2(times = 1) {
            if (this.isMuted || !this.beepAudio2) return;
            let count = 0;
            const playOnce = () => {
                if (count >= times) return;
                this.beepAudio2.currentTime = 0;
                this.beepAudio2.play().catch(() => console.warn('Audio play blocked'));
                count++;
                if (count < times) setTimeout(playOnce, 500);
            };
            playOnce();
        },

        clearBeepFlags() {
            Object.keys(localStorage)
                .filter(k => k.startsWith('beep_'))
                .forEach(k => localStorage.removeItem(k));
        },

        toggleMute() {
            this.isMuted = !this.isMuted;
            localStorage.setItem('audio_muted', this.isMuted);

            if (!this.isMuted) {
                const audio = new Audio("{{ asset('assets/audio/beep-01.mp3') }}");
                audio.volume = 0.01;
                audio.play().catch(() => console.log('Izin audio belum diberikan browser'));
            }
        },

        // =============================================================
        // SAFE RELOAD
        // Reload hanya ketika COUNTDOWN sehingga tidak memotong adzan/iqomah/sholat.
        // =============================================================
        setupDailyReload() {
            const delay = 3 * 60 * 60 * 1000;
            if (this.reloadTimer) clearTimeout(this.reloadTimer);
            this.reloadTimer = setTimeout(() => this.tryReload(), delay);
        },

        tryReload() {
            if (this.mode !== 'COUNTDOWN') {
                this.reloadTimer = setTimeout(() => this.tryReload(), 60 * 1000);
                return;
            }

            if (!navigator.onLine) {
                this.reloadTimer = setTimeout(() => this.tryReload(), 5 * 60 * 1000);
                return;
            }

            fetch('/ping', { cache: 'no-store' })
                .then(response => {
                    if (!response.ok) throw new Error('Ping gagal');
                    location.reload();
                })
                .catch(() => {
                    this.reloadTimer = setTimeout(() => this.tryReload(), 5 * 60 * 1000);
                });
        },
    }));

    window.Echo.channel('display-updated')
        .listen('.DisplayUpdates', e => {
            if (e.type === 'reloadDisplay') {
                location.reload();
            }
        })
</script>
@endscript
