<?php

use App\Models\City;
use Livewire\Component;
use Livewire\Attributes\On;
use App\Models\PrayerSchedule;
use Illuminate\Support\Facades\DB;
use App\Events\DisplayUpdatesEvent;
use Illuminate\Support\Facades\Http;

new class extends Component
{
    public $endpoint;
    public $kota;
    public $tahun;
    public $bulan;
    public $labelKota;

    //utk dropdown
    public $years = [];
    public $months = [
            '01' => 'Januari',
            '02' => 'Februari',
            '03' => 'Maret',
            '04' => 'April',
            '05' => 'Mei',
            '06' => 'Juni',
            '07' => 'Juli',
            '08' => 'Agustus',
            '09' => 'September',
            '10' => 'Oktober',
            '11' => 'November',
            '12' => 'Desember',
        ];

    public function mount()
    {
        $this->defaultData();
    }

    #[On('refreshData')]
    public function defaultData()
    {
        $dt_profile = DB::table('masjid_settings')->where('key', 'masjidProfile')->first();
        if($dt_profile) {
            $profiles = json_decode($dt_profile->value, true);
            $this->kota = $profiles['kota'];
            $city = DB::table('cities')->where('lokasi_id', $this->kota)->first();
            $this->labelKota = $city->lokasi ?? '';
        }
        $this->endpoint = env('API_JADWAL_SHOLAT', 'https://api.myquran.com/v2/sholat/jadwal');
        $this->years = range(date('Y'), date('Y') + 5);
        $this->tahun = date('Y');
        $this->bulan = date('m');
    }

    public function sinkronisasi()
    {
        $this->validate([
            'endpoint' => 'required',
            'kota' => 'required',
            'tahun' => 'required',
            'bulan' => 'required',
        ]);

        $baseUrl = "{$this->endpoint}/{$this->kota}/{$this->tahun}/{$this->bulan}";
        $response = Http::get($baseUrl);

        if ($response->successful()) {
            $data = $response->json();
            if($data['status'] == true) {
                PrayerSchedule::syncFromApi($this->kota,$this->tahun, $this->bulan);
                $this->closeModal();
                $this->dispatch('refreshPrayertime'); //mentrigger refresh di halaman prayertime
                $this->reset('kota', 'labelKota', 'tahun', 'bulan');
                $this->dispatch('toast', type: 'success', message: 'Sinkronisasi berhasil.');
                $this->dispatch('refreshData');
            }else{
                $this->dispatch('toast', type: 'error', message: 'Gagal melakukan sinkronisasi. Cek kembali API Endpoint Anda.');
            }
        }else{
            $this->dispatch('toast', type: 'error', message: 'Gagal melakukan sinkronisasi. Cek kembali API Endpoint Anda.');
        }
        DisplayUpdatesEvent::dispatch('reloadDisplay', []);
    }

    #[On('openModalSinkronisasi')]
    public function handleOpenModalSinkronisasi()
    {
        $this->dispatch('open-modal', name: 'modal_sinkroninisasi');
    }

    public function openModalKota()
    {
        $this->dispatch('openModalKota'); //mentrigger modal di halaman cities
    }

    #[On('citySelected')]
    public function updatedSelectCity($id, $name)
    {
        $this->labelKota = $name;
        $this->kota = $id;
    }

    public function closeModal()
    {
        $this->dispatch('close-modal', name: 'modal_sinkroninisasi');
        $this->reset('kota', 'labelKota', 'tahun', 'bulan');
        $this->dispatch('refreshData');
    }

};
?>


{{-- blade --}}
<x-custom-modal name="modal_sinkroninisasi" width="w-2xl" :dismissible="false" :closable="false">
    <!--loader-->
    <div wire:loading class="fixed left-0 top-0 bg-white opacity-70 text-center w-full h-full" style="z-index: 51">
        <div class="min-h-full flex items-center justify-center">
            <div role="status">
                <div class="page-loader"></div>
            </div>
        </div>
    </div>
    <!--loader-->

    <div class="mb-3">
        <h4 class="font-semibold text-lg">Sinkronisasi Jadwal Sholat</h4>
        <flux:text>Melalui fitur ini Anda dapat melakukan sinkronisasi database jadwal sholat dengan API Endpoint</flux:text>
    </div>
    <form wire:submit.prevent="sinkronisasi">
        <div class="mb-3">
            <flux:label class="text-sm">Endpoint API</flux:label>
            <flux:input wire:model="endpoint" />
        </div>
        <div class="mb-3">
            <flux:label class="text-sm">Kota</flux:label>
            <flux:input wire:model="kota" hidden/>
            <flux:input wire:model="labelKota" type="text" wire:click="openModalKota" placeholder="Klik untuk cari data kota" autocomplete="off" icon="magnifying-glass"/>
        </div>
        <div class="mb-3">
            <flux:label class="text-sm">Tahun</flux:label>
            <flux:select wire:model="tahun">
                @foreach ($years as $year)
                    <flux:select.option value="{{ $year }}">{{ $year }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>
        <div class="mb-3">
            <flux:label class="text-sm">Bulan</flux:label>
            <flux:select wire:model="bulan">
                @foreach ($months as $key => $month)
                    <flux:select.option value="{{ $key }}">{{ $month }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>
        <flux:separator variant="subtle" class="mt-3" />
        <div class="mt-3 flex gap-2 justify-end">
            <flux:button wire:click="closeModal" variant="primary" color="amber" class="cursor-pointer">Close</flux:button>
            <flux:button type="submit" variant="primary" class="cursor-pointer">Sync Now</flux:button>
        </div>
    </form>

    <livewire:pages::adminpage.cities />
</x-custom-modal>
