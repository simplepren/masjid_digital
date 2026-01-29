<?php

use Livewire\Component;
use Carbon\Carbon;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use App\Models\PrayerSchedule;
use App\Events\DisplayUpdatesEvent;
use App\Models\PrayerCorrection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

new class extends Component
{
    public $currentDate;
    public $minDate = '2026-01-01';
    public array $correction = [];

    public function mount()
    {
        $dt_corrections = DB::table('settings')->where('key', 'corrections')->first();
        if ($dt_corrections) {
            $this->correction = json_decode($dt_corrections->value, true);
        }
        $this->currentDate = Carbon::now()->startOfMonth();
    }

    #[On('refreshData')]
    public function render()
    {
        $year = $this->currentDate->year;
        $month = $this->currentDate->month;

        // Ambil data berdasarkan bulan dan tahun yang terpilih
        $schedules = PrayerSchedule::whereYear('date', $year)
            ->whereMonth('date', $month)
            ->orderBy('date', 'asc')
            ->get();

        return $this->view([
            'schedules' => $schedules,
            'monthName' => $this->currentDate->translatedFormat('F Y'),
        ]);
    }

    public function nextMonth()
    {
        $this->currentDate = Carbon::parse($this->currentDate)->addMonth()->startOfMonth();
    }

    public function previousMonth()
    {
        $this->currentDate = Carbon::parse($this->currentDate)->subMonth()->startOfMonth();
    }

    public function syncronize()
    {
        $this->dispatch('openModalSinkronisasi'); //mentrigger modal di halaman syncronize
    }

    public function correctionForm()
    {
        $this->validate([
            'correction.subuh' => 'required|numeric',
            'correction.dzuhur' => 'required|numeric',
            'correction.ashar' => 'required|numeric',
            'correction.maghrib' => 'required|numeric',
            'correction.isya' => 'required|numeric',
        ]);

        // Update ke database
        DB::table('settings')
            ->where('key', 'corrections')
            ->update([
                'value' => $this->correction,
                'updated_at' => now(),
        ]);
        $this->closeModalCorrection();
        $this->dispatch('toast', type: 'success', message: 'Koreksi waktu sholat berhasil disimpan.');
        event(new DisplayUpdatesEvent('correctionUpdated', $this->correction));
    }

    public function prayerCorrection()
    {
        $this->dispatch('open-modal', name: 'modal_correction');
    }

    public function closeModalCorrection()
    {
        $this->dispatch('close-modal', name: 'modal_correction');
    }

    public function resetForm()
    {
        $this->reset(['endpoint', 'kota', 'daftarKota']);
        $this->mount();
    }
};
?>

<div>
    <!--loader-->
    <div wire:loading class="fixed left-0 top-0 bg-white opacity-70 text-center w-full h-full" style="z-index: 51">
        <div class="min-h-full flex items-center justify-center">
            <div role="status">
                <div class="page-loader"></div>
            </div>
        </div>
    </div>
    <!--loader-->
    <div class="mb-6">
        <flux:heading size="xl">Pengaturan Jadwal Sholat</flux:heading>
    </div>
    <div class="flex items-center justify-between mb-2">
        <div class="flex gap-2">
            <flux:button size="sm" variant="primary" wire:click="prayerCorrection" class="cursor-pointer">Koreksi Jadwal Sholat</flux:button>
            <flux:button size="sm" variant="primary" color="cyan" wire:click="syncronize" class="cursor-pointer">Sinkronisasi Jadwal Sholat</flux:button>
        </div>
        <div class="flex items-center space-x-4 px-2 rounded-lg">
            @if($currentDate->translatedFormat('Y-m-d') > $minDate)
            <button wire:click="previousMonth" class="p-2 hover:bg-white rounded-md transition shadow-sm cursor-pointer">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                </svg>
            </button>
            @endif
            <span class="text-lg font-semibold min-w-37.5 text-center">
                {{ $monthName }}
            </span>
            <button wire:click="nextMonth" class="p-2 hover:bg-white rounded-md transition shadow-sm cursor-pointer">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                </svg>
            </button>
        </div>
    </div>

    <div class="overflow-x-auto border rounded-lg">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-teal-600 text-white font-semibold text-sm">
                <tr>
                    <th class="px-4 py-3 text-left uppercase tracking-wider">Hari</th>
                    <th class="px-4 py-3 text-left uppercase tracking-wider">Tanggal</th>
                    <th class="px-4 py-3 text-center uppercase tracking-wider">Subuh
                        @if($correction['subuh'] != 0)
                        <span class="text-xs text-yellow-300 italic lowercase">{{ $correction['subuh'] > 0 ? '+' : '' }}{{ $correction['subuh'] }} menit</span>
                        @endif 
                    </th>
                    <th class="px-4 py-3 text-center uppercase tracking-wider">Syuruq</th>
                    <th class="px-4 py-3 text-center uppercase tracking-wider">Dhuha</th>
                    <th class="px-4 py-3 text-center uppercase tracking-wider">Dzuhur
                        @if($correction['dzuhur'] != 0)
                        <span class="text-xs text-yellow-300 italic lowercase">{{ $correction['dzuhur'] > 0 ? '+' : '' }}{{ $correction['dzuhur'] }} menit</span>
                        @endif 
                    </th>
                    <th class="px-4 py-3 text-center uppercase tracking-wider">Ashar
                        @if($correction['ashar'] != 0)
                        <span class="text-xs text-yellow-300 italic lowercase">{{ $correction['ashar'] > 0 ? '+' : '' }}{{ $correction['ashar'] }} menit</span>
                        @endif 
                    </th>
                    <th class="px-4 py-3 text-center uppercase tracking-wider">Maghrib
                        @if($correction['maghrib'] != 0)
                        <span class="text-xs text-yellow-300 italic lowercase">{{ $correction['maghrib'] > 0 ? '+' : '' }}{{ $correction['ashar'] }} menit</span>
                        @endif 
                    </th>
                    <th class="px-4 py-3 text-center uppercase tracking-wider">Isya
                        @if($correction['isya'] != 0)
                        <span class="text-xs text-yellow-300 italic lowercase">{{ $correction['isya'] > 0 ? '+' : '' }}{{ $correction['ashar'] }} menit</span>
                        @endif 
                    </th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                @forelse($schedules as $schedule)
                    @php 
                        $dt = \Carbon\Carbon::parse($schedule->date);
                        $isToday = $dt->isToday();
                        $isFriday = $dt->isFriday();
                    @endphp
                    <tr class="{{ $isToday ? 'bg-green-100' : '' }} hover:bg-gray-50 transition">
                        <td class="px-4 py-3 whitespace-nowrap text-sm {{ $isFriday ? 'text-red-600 font-bold' : 'text-gray-900' }}">
                            {{ $dt->translatedFormat('l') }}
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-600">
                            {{ $dt->translatedFormat('d M Y') }}
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap text-sm text-center font-medium">{{ $schedule->subuh }}</td>
                        <td class="px-4 py-3 whitespace-nowrap text-sm text-center text-gray-500">{{ $schedule->terbit }}</td>
                        <td class="px-4 py-3 whitespace-nowrap text-sm text-center text-gray-500">{{ $schedule->dhuha }}</td>
                        <td class="px-4 py-3 whitespace-nowrap text-sm text-center font-medium">{{ $schedule->dzuhur }}</td>
                        <td class="px-4 py-3 whitespace-nowrap text-sm text-center font-medium">{{ $schedule->ashar }}</td>
                        <td class="px-4 py-3 whitespace-nowrap text-sm text-center font-medium">{{ $schedule->maghrib }}</td>
                        <td class="px-4 py-3 whitespace-nowrap text-sm text-center font-medium">{{ $schedule->isya }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-4 py-10 text-center text-gray-500">
                            Data jadwal sholat untuk bulan ini belum tersedia. <br>
                            <button wire:click="syncronize" class="mt-2 text-blue-600 underline cursor-pointer">Klik untuk sinkronisasi sekarang</button>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <x-custom-modal name="modal_correction" width="w-lg" :dismissible="false" :closable="false">
        <div class="mb-3">
            <h4 class="font-semibold text-lg">Koreksi Jadwal Sholat</h4>
            <flux:text>Silakan koreksi jadwal sholat dengan menambah/mengurangi waktu sholat dari jadwal seharusnya</flux:text>
        </div>
        <form wire:submit.prevent="correctionForm">
            <div class="mb-3">
                <flux:label class="text-sm">Subuh/Fajr</flux:label>
                <flux:input wire:model="correction.subuh" type="number" min="-10" max="10" />
            </div>
            <div class="mb-3">
                <flux:label class="text-sm">Dzuhur</flux:label>
                <flux:input wire:model="correction.dzuhur" type="number" min="-10" max="10" />
            </div>
            <div class="mb-3">
                <flux:label class="text-sm">Azhar</flux:label>
                <flux:input wire:model="correction.ashar" type="number" min="-10" max="10" />
            </div>
            <div class="mb-3">
                <flux:label class="text-sm">Maghrib</flux:label>
                <flux:input wire:model="correction.maghrib" type="number" min="-10" max="10" />
            </div>
            <div class="mb-3">
                <flux:label class="text-sm">Isya</flux:label>
                <flux:input wire:model="correction.isya" type="number" min="-10" max="10" />
            </div>
            <flux:separator variant="subtle" class="mt-3" />
            <div class="mt-3 flex gap-2 justify-end">
                <flux:button wire:click="closeModalCorrection" variant="primary" color="amber" class="cursor-pointer">Close</flux:button>
                <flux:button type="submit" variant="primary" class="cursor-pointer">Simpan</flux:button>
            </div>
        </form>
    </x-custom-modal>

    <livewire:pages::adminpage.synchronize />

</div>
