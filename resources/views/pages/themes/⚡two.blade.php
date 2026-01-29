<?php

use Carbon\Carbon;
use Livewire\Component;
use Livewire\Attributes\On;
use App\Models\PrayerSchedule;
use Illuminate\Support\Facades\DB;

new class extends Component
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
        $defaultTexts = [
            'Sholat berjamaah itu lebih utama daripada sholat sendiri',
            'Mohon pastikan alat komunikasi dalam mode senyap atau dimatikan selama sholat berjamaah',
        ];

        $texts = DB::table('running_texts')
            ->where('active', true)
            ->orderBy('order_index')
            ->pluck('text')
            ->toArray();
        
        $this->runningTexts = !empty($texts) ? $texts : $defaultTexts;
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
    })"
    x-init="init()" 
    x-on:prayers-updated="updatePrayers($event.detail.prayers); updateSettings($event.detail.settings);"
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
        <div class="absolute inset-0 bg-black/30"></div>
    </div>
    <div class="absolute inset-0 bg-black/30"></div>
    <div class="absolute top-0 inset-x-0 h-14 bg-gray-900/90 text-white px-8 flex items-center justify-end z-10">
        <div class="text-2xl flex items-center gap-3">
            <div class="text-green-400 animate-pulse"><flux:icon.dot /></div>
            <span class="font-semibold text-teal-100" x-text="hari"></span>
            <span x-text="tanggalMasehi"></span>
            <span class="text-teal-500">/</span>
            <span class="text-yellow-200" x-text="hijri"></span>
        </div>
    </div>
    <div class="absolute top-0 left-0 w-6/12 bg-linear-to-r from-teal-800 via-teal-700 to-teal-500 h-32 rounded-br-[5rem] flex items-center gap-6 p-6 shadow-2xl z-20">
        <div class="shrink-0">
            @if($logo)
                <img src="{{ asset('assets/images/'.$logo) }}" class="w-20 h-20 object-contain" alt="Logo">
            @else
                <span class="text-xl text-white">Logo</span>
            @endif
        </div>
        <div class="flex flex-col">
            <h1 class="text-white text-4xl font-bold tracking-tight">{{ $nama_masjid }}</h1>
            <p class="text-teal-50 opacity-90 line-clamp-1">{{ $alamat }}</p>
            <p class="text-teal-50 opacity-90 line-clamp-1">Telp. {{ $telp }}</p>
        </div>
    </div>

    <div class="fixed top-38 left-8 opacity-20 hover:opacity-100 transition-opacity duration-500">
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

    <div class="absolute top-20 right-38 h-12 flex items-center w-40 z-20">
        <div class="bg-teal-700 p-2.5 rounded-l-xl shadow-lg">
            <flux:icon.clock class="w-7 h-7 text-white" />
        </div>
        <div class="bg-white/95 backdrop-blur flex items-center gap-4 px-6 h-12 rounded-r-xl text-xl shadow-lg border-y-2 border-r-2 border-teal-600">
            <span class="text-gray-600 font-medium" x-text="displayLabel(getNextPrayerObject())"></span>
            <span class="font-bold text-teal-700 tabular-nums" x-text="countdown"></span>
        </div>
    </div>

    <div class="absolute bottom-12 right-0 left-48 h-28 bg-teal-900/80 backdrop-blur-md grid grid-cols-7 gap-0.5 p-0.5 shadow-2xl z-10">
        <template x-for="(p, i) in prayers" :key="p.key">
            <div class="flex flex-col items-center justify-center transition-all duration-500" 
                :class="currentIndex() === i ? 'bg-yellow-700 border-2 border-yellow-500 text-white scale-105 z-20 shadow-lg' : 'bg-teal-700 text-white'">
                <span class="text-xl font-medium" x-text="displayLabel(p)"></span>
                <span class="text-4xl font-bold" x-text="p.time"></span>
                <template x-if="currentIndex() === i">
                    <span class="text-[12px] uppercase tracking-widest font-black text-yellow-200 mt-1">Sekarang</span>
                    
                </template>
            </div>
        </template>
    </div>

    <div class="absolute -bottom-2.5 -left-2 z-50 transform scale-110 w-60 h-60 flex items-center justify-center">
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
            class="absolute w-50 h-50 rounded-full bg-teal-600 flex justify-center items-center shadow-2xl">
            <div class="bg-white w-44 h-44 rounded-full flex flex-col justify-center items-center shadow-lg">
                <div class="text-5xl font-bold text-teal-700 tabular-nums" x-text="time"></div>
            </div>
        </div>
    </div>

    <div class="absolute bottom-0 right-0 left-32 h-12 bg-black/90 flex items-center overflow-hidden border-t border-teal-500/30 z-10">
        <div class="marquee-container w-full relative h-full flex items-center">
            <div class="marquee-content shrink-0 flex items-center">
                @foreach($runningTexts as $text)
                    <span class="text-white text-2xl px-12 flex items-center whitespace-nowrap">
                        <span class="text-yellow-500 mr-3">✦</span> 
                        {{ $text }}
                    </span>
                @endforeach
                @foreach($runningTexts as $text)
                    <span class="text-white text-2xl px-12 flex items-center whitespace-nowrap">
                        <span class="text-yellow-500 mr-3">✦</span> 
                        {{ $text }}
                    </span>
                @endforeach
                @foreach($runningTexts as $text)
                    <span class="text-white text-2xl px-12 flex items-center whitespace-nowrap">
                        <span class="text-yellow-500 mr-3">✦</span> 
                        {{ $text }}
                    </span>
                @endforeach
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
                    <div x-ref="flipTunggal" id="flip-container"></div>
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
                    :style="`width: ${ (sholatRemaining / totalSholatDuration) * 100 }%`"
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
            // console.log('updateDuration', newDuration);
            this.duration = newDuration;
            this.startTimer();
        },

        destroy() {
            this.stopTimer();
        }
    }));

    Alpine.data('masjidApp', ({ prayerTimes, durasiAdzan, durasiIqomah, durasiSholat, hijriOffset }) => ({
        // --- DATA & STATE ---
        prayers: prayerTimes,
        mode: 'COUNTDOWN', // ADZAN, IQOMAH, SHOLAT, COUNTDOWN
        nextIndex: 0,
        time: '',
        hijriOffset: hijriOffset,
        hari: '',
        baseHijriDate: null, // simpan tanggal hijri default tanpa offset
        hijri: '',
        tanggalMasehi: '',
        countdown: '',
        isMuted: localStorage.getItem('audio_muted') === 'true',
        showAnalog: true,
        isClockTransitioning: false,

        // Timer States
        adzanRemaining: 0,
        iqomahRemaining: 0,
        sholatRemaining: 0,
        totalSholatDuration: 0,
        currentPrayerName: '',

        //durasi sholat
        durasiAdzan: durasiAdzan,
        durasiIqomah: durasiIqomah,
        durasiSholat: durasiSholat,

        // Internal
        timer: null,
        beepAudio1: null,
        beepAudio2: null,
        todayKey: new Date().toDateString(),

        
        init() {
            // 1. Setup Audio
            this.beepAudio1 = new Audio("{{ asset('assets/audio/beep-01.mp3') }}");
            this.beepAudio2 = new Audio("{{ asset('assets/audio/beep-02.mp3') }}");

            // 2. Initial Run
            this.updateClock();
            this.updateDate();
            this.loopClockSwitch();

            // 3. Restore State atau Start Fresh
            const restored = this.restoreState();
            if (!restored) {
                this.updateNextIndex();
                this.tickCountdown();
            }

            // 4. Master Interval (Setiap 1 Detik)
            setInterval(() => {
                this.updateClock();

                if (this.mode === 'COUNTDOWN') {
                    this.checkNewDay();
                    this.updateNextIndex();
                    this.tickCountdown();
                }
            }, 1000);

            // Update Tanggal tiap menit
            setInterval(() => {
                this.updateDate();
            }, 60000);
        },

        updatePrayers(newData) {
            // console.log("Jadwal Sholat Diperbarui:", newData);
            if (!Array.isArray(newData)) return;
            this.prayers = newData;
            this.updateNextIndex();
            this.tickCountdown();
        },

        updateSettings(settings) {
            if (!settings) return;
            // console.log("Settings Diperbarui:", settings);
            if (settings.durasiAdzan) {
                this.durasiAdzan = settings.durasiAdzan;
            }
            if (settings.durasiIqomah) {
                this.durasiIqomah = settings.durasiIqomah;
            }
            if (settings.durasiSholat) {
                this.durasiSholat = settings.durasiSholat;
            }
            if (Object.prototype.hasOwnProperty.call(settings, 'hijriOffset')) {
                this.hijriOffset = Number(settings.hijriOffset);
                this.applyHijriOffset();
            }
        },

        updateClock() {
            this.time = new Date().toLocaleTimeString('en-GB', {
                hour: '2-digit', minute: '2-digit', hour12: false
            });
        },

        applyHijriOffset() {
            if (!this.baseHijriDate) return;

            const hijriDate = new Date(this.baseHijriDate);
            // console.log('tanggal hijri', hijriDate);
            hijriDate.setDate(hijriDate.getDate() + this.hijriOffset);

            this.hijri = new Intl.DateTimeFormat(
                'id-ID-u-ca-islamic-umalqura',
                { day: 'numeric', month: 'long', year: 'numeric' }
            ).format(hijriDate);
        },


        updateHijriDate(now = new Date()) {
            // BASE DATE (tanpa offset)
            const baseDate = new Date(now);

            const isAfterMaghrib =
                localStorage.getItem('hijri_is_after_maghrib') === '1';

            if (isAfterMaghrib) {
                baseDate.setDate(baseDate.getDate() + 1);
            }

            // simpan base hijri
            this.baseHijriDate = new Date(baseDate);

            // apply offset ABSOLUTE
            baseDate.setDate(baseDate.getDate() + this.hijriOffset);

            this.hijri = new Intl.DateTimeFormat(
                'id-ID-u-ca-islamic-umalqura',
                { day: 'numeric', month: 'long', year: 'numeric' }
            ).format(baseDate);
        },


        updateDate() {
            const now = new Date();

            const namaHari = ["Ahad", "Senin", "Selasa", "Rabu", "Kamis", "Jumat", "Sabtu"];
            this.hari = namaHari[now.getDay()] + ',';

            this.tanggalMasehi = now.toLocaleDateString('id-ID', {
                day: '2-digit',
                month: 'long',
                year: 'numeric'
            });

            // hijri ikut dihitung
            this.updateHijriDate(now);
        },

        tickCountdown() {
            const now = new Date();
            const event = this.prayers[this.nextIndex];
            if (!event) return;

            const [h, m] = event.time.split(':').map(Number);
            const target = new Date(now);

            target.setHours(h, m, 0, 0);

            if (target <= now) target.setDate(target.getDate() + 1);
            const diff = Math.floor((target - now) / 1000);

            if (diff <= 0) {
                if (event.hasAdzan) this.startAdzan();
                return;
            }

            const hrs = String(Math.floor(diff / 3600)).padStart(2, '0');
            const mins = String(Math.floor((diff % 3600) / 60)).padStart(2, '0');
            const secs = String(diff % 60).padStart(2, '0');

            this.countdown = `-${hrs}:${mins}:${secs}`;
        },


        saveState() {
            localStorage.setItem('masjid_state', JSON.stringify({
                mode: this.mode,
                nextIndex: this.nextIndex,
                remaining: this.getRemaining(),
                timestamp: Date.now()
            }));
        },

        restoreState() {
            const raw = localStorage.getItem('masjid_state');
            if (!raw) return false;

            const data = JSON.parse(raw);
            const remaining = data.remaining - Math.floor((Date.now() - data.timestamp) / 1000);

            if (remaining <= 0) {
                this.clearState();
                return false;
            }

            this.nextIndex = data.nextIndex;
            this.mode = data.mode;

            if (this.mode === 'ADZAN') this.startAdzan(remaining, true);
            else if (this.mode === 'IQOMAH') this.startIqomah(remaining);
            else if (this.mode === 'SHOLAT') this.startSholat(remaining);
            return true;
        },

        getRemaining() {
            if (this.mode === 'ADZAN') return this.adzanRemaining;
            if (this.mode === 'IQOMAH') return this.iqomahRemaining;
            if (this.mode === 'SHOLAT') return this.sholatRemaining;
            return 0;
        },

        clearState() { 
            localStorage.removeItem('masjid_state'); 
        },

        loopClockSwitch() {
            setInterval(() => {
                if (this.isClockTransitioning) return;

                this.isClockTransitioning = true;
                this.showAnalog = !this.showAnalog;

                setTimeout(() => {
                    this.isClockTransitioning = false;
                }, 1200); // sedikit di atas durasi transition
            }, 10000);
        },

        /* ================= NEW DAY ================= */
        onNewDay() {
            this.clearTimer();
            this.clearState();
            this.clearBeepFlags();

            this.mode = 'COUNTDOWN';
            this.countdown = '';

            this.updateNextIndex();
            localStorage.setItem('last_daily_reload', new Date().toDateString());

            // 🔥 trigger Livewire refresh
            this.$dispatch('refresh-schedule'); //refresh jadwal sholat pada 00.01 WIB            
            localStorage.removeItem('hijri_is_after_maghrib'); // Reset flag Hijriah
            window.dispatchEvent(new Event('hijri-after-maghrib')); // Refresh tampilan
        },
        
        // Called when a new day is detected
        checkNewDay() {
            const today = new Date().toDateString();
            if (today !== this.todayKey) {
                this.todayKey = today;
                this.onNewDay();
            }
        },

        currentIndex() {
            if (this.nextIndex === null) return -1;

                // KASUS A: Jika sekarang sudah lewat Isya (mengejar Subuh besok, nextIndex = 0)
                if (this.nextIndex === 0) {
                    const now = new Date();
                    const nowMin = now.getHours() * 60 + now.getMinutes();
                    
                    // Ambil waktu subuh hari ini
                    const [h, m] = this.prayers[0].time.split(':').map(Number);
                    const subuhMin = h * 60 + m;
                    if (nowMin > subuhMin) {
                        return 6;
                    }
                    return 6;
                }
                return this.nextIndex - 1;
        },

        updateNextIndex() {
            if (this.mode !== 'COUNTDOWN') return; // Kunci index jika sedang adzan/sholat
            if (!this.prayers || this.prayers.length === 0) return;

            const now = new Date();
            const nowMin = now.getHours() * 60 + now.getMinutes();
            
            // 1. Logika Hijriah (Maghrib)
            const maghrib = this.prayers.find(p => p.key === 'maghrib');
            if (maghrib) {
                const [h, m] = maghrib.time.split(':').map(Number);
                const isPastMaghrib = nowMin >= (h * 60 + m);
                const currentFlag = localStorage.getItem('hijri_is_after_maghrib');

                if (isPastMaghrib && currentFlag !== '1') {
                    localStorage.setItem('hijri_is_after_maghrib', '1');
                    window.dispatchEvent(new Event('hijri-after-maghrib'));
                } else if (!isPastMaghrib && currentFlag === '1') {
                    localStorage.removeItem('hijri_is_after_maghrib');
                    window.dispatchEvent(new Event('hijri-after-maghrib'));
                }
            }

            // 2. Cari Next Index
            let found = false;
            for (let i = 0; i < this.prayers.length; i++) {
                const [h, m] = this.prayers[i].time.split(':').map(Number);
                if ((h * 60 + m) > nowMin) {
                    this.nextIndex = i;
                    found = true;
                    break;
                }
            }
            
            // Jika tidak ada yang lebih besar (sudah malam), maka next adalah Subuh besok
            if (!found) this.nextIndex = 0;
        },

        getNextPrayerObject() {
            return this.prayers[this.nextIndex] || this.prayers[0];
        },

        /* ================= Label Sholat ================= */
        displayLabel(prayer) {
            // PROTEKSI: Jika prayer undefined, kembalikan string kosong
            if (!prayer) return ""; 

            if (prayer.key === 'dzuhur' && new Date().getDay() === 5) {
                return "Jum'ah";
            }
            return prayer.label;
        },

        /* ================= MODE ADZAN ================= */
        startAdzan(remaining = null, isRestored = false) {
            this.clearTimer();
            this.mode = 'ADZAN';
            const currentEvent = this.prayers[this.nextIndex];
            if(!currentEvent) return this.reset();
            
            this.adzanRemaining = remaining ?? (this.durasiAdzan[currentEvent.key] || 120);

            this.initFlip(this.adzanRemaining);
            
            // Jangan bunyikan beep jika ini hasil dari refresh browser (isRestored)
            if (!isRestored) {
                this.playBeep1(1); 
            }

            this.timer = setInterval(() => {
                if (this.adzanRemaining > 0) {
                    this.adzanRemaining--;
                    this.saveState();
                } else {
                    clearInterval(this.timer);
                    setTimeout(() => this.finishAdzan(), 1000);
                }
            }, 1000);
        },

        finishAdzan() {
            this.clearTimer();
            const currentPrayer = this.prayers[this.nextIndex];
            
            // Cek apakah hari ini Jumat dan shalatnya adalah Dzuhur
            if (currentPrayer && this.isFriday() && currentPrayer.key === 'dzuhur') {
                this.startSholat(); 
            } else {
                this.startIqomah();
            }
        },

        // ... startIqomah dan startSholat tetap mirip, pastikan memanggil saveState()
        startIqomah(remaining = null) {
            this.clearTimer();
            this.mode = 'IQOMAH';
            const currentEvent = this.prayers[this.nextIndex];
            this.iqomahRemaining = remaining ?? (this.durasiIqomah[currentEvent.key] || 300);
            
            this.initFlip(this.iqomahRemaining);
            this.timer = setInterval(() => {
                if (this.iqomahRemaining > 0) {
                    this.iqomahRemaining--;
                    this.saveState();
                } else {
                    clearInterval(this.timer);
                    this.playBeep2(1);
                    setTimeout(() => this.startSholat(), 4000);
                }
            }, 1000);
        },

        startSholat(remaining = null) {
            const currentEvent = this.prayers[this.nextIndex];
            if (!currentEvent) return this.reset();

            this.clearTimer();
            this.mode = 'SHOLAT';
            this.currentPrayerName = this.displayLabel(currentEvent).toUpperCase();
            
            const key = currentEvent.key;
            if (this.isFriday() && key === 'dzuhur') {
                this.totalSholatDuration = this.durasiSholat['jumat'] || 2400;
            } else {
                this.totalSholatDuration = this.durasiSholat[key] || 600;
            }

            this.sholatRemaining = remaining ?? this.totalSholatDuration;
            this.saveState();

            this.timer = setInterval(() => {
                if (this.sholatRemaining > 0) {
                    this.sholatRemaining--;
                    this.saveState();
                } else {
                    this.reset();
                }
            }, 1000);
        },

        reset() {
            this.clearTimer();
            this.clearState();
            this.mode = 'COUNTDOWN';
            this.updateNextIndex();
            this.tickCountdown();
        },

        /* ================= UTIL ================= */
        clearTimer() {
            if (this.timer) {
                clearInterval(this.timer);
                this.timer = null;
            }

            // Bersihkan instance FlipClock jika mode berpindah
            if (this.flipClockInstance) {
                this.flipClockInstance = null;
            }
        },

        isFriday() {
            return new Date().getDay() === 5;
        },

        // --- FUNGSI AUDIO YANG AMAN ---
        playBeep1(times = 1) {
            if (this.isMuted || !this.beepAudio1) return;
            let count = 0;
            const playOnce = () => {
                if (count >= times) return;
                this.beepAudio1.currentTime = 0;
                this.beepAudio1.play().catch(e => console.warn("Audio play blocked"));
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
                this.beepAudio2.play().catch(e => console.warn("Audio play blocked"));
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

        // Mute audio
        toggleMute() {
            this.isMuted = !this.isMuted;
            localStorage.setItem('audio_muted', this.isMuted);
            
            // Trik: Mainkan suara kosong (silent) untuk "memancing" izin browser
            if (!this.isMuted) {
                const audio = new Audio('assets/audio/beep-01.mp3');
                audio.volume = 1; // Tidak terdengar
                audio.play().catch(e => console.log("Izin audio didapat"));
            }
        },

        initFlip(detik) {
            // 1. Jika sudah ada instance, kita coba hentikan dulu (tergantung library yang dipakai)
            if (this.flipClockInstance) {
                this.flipClockInstance = null;
            }

            this.$nextTick(() => {
                const container = this.$refs.flipTunggal;
                if (!container) return;

                // 2. Paksa kosongkan container
                container.innerHTML = '';
                
                const targetTime = new Date(Date.now() + (detik * 1000) + 500);
                
                // 3. Gunakan pengecekan sederhana untuk mencegah eksekusi ganda dalam waktu yang sangat berdekatan
                if (container.children.length > 0) return;

                this.flipClockInstance = FlipClock.flipClock({
                    parent: container,
                    face: FlipClock.elapsedTime({
                        to: targetTime,
                        format: '[mm]:[ss]'
                    }),
                    theme: FlipClock.theme({
                        dividers: ':',
                        css: FlipClock.css({ 
                            fontSize: '10rem', 
                            color: '#927a38',
                            backgroundColor: 'transparent' 
                        })
                    })
                });
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
