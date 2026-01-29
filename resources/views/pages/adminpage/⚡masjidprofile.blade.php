<?php

use App\Models\City;
use App\Events\DisplayUpdatesEvent;
use Livewire\Component;
use Livewire\Attributes\On;
use Livewire\WithFileUploads;
use Illuminate\Support\Facades\DB;

new class extends Component
{
    use WithFileUploads;

    public int $userId;
    public array $profile = [];
    public string $labelKota = '';
    public $logo; // Properti khusus untuk file upload baru

    public function mount()
    {
        $this->userId = auth()->user()->id;

        $dt_profile = DB::table('masjid_settings')->where('key', 'masjidProfile')->first();
        if($dt_profile) {
            $this->profile = json_decode($dt_profile->value, true) ?? [];
        }

        // Tambahkan pengecekan isset agar tidak error jika profile kosong
        if(isset($this->profile['kota'])) {
            $cities = City::where('lokasi_id', $this->profile['kota'])->first();
            $this->labelKota = $cities->lokasi ?? '';
        }
    }

    public function setupMasjid()
    {
        // dd($this->profile, $this->logo);
        $this->validate([
            'profile.nama_masjid' => 'required',
            'profile.alamat'      => 'required',
            'profile.telp'        => 'required',
            'profile.kota'        => 'required',
            'logo'                => 'nullable|image|max:2048', // Validasi properti $logo
        ]);
       
        // Logika simpan Logo
        if ($this->logo) {
            $logoName = uniqid() . '-' . $this->logo->getClientOriginalName();
            $this->logo->storeAs('assets/images', $logoName, 'real_public');
            
            // Simpan nama file ke array profile untuk masuk ke DB
            $this->profile['logo'] = $logoName;
        }

        // Update DB
        DB::table('masjid_settings')
            ->where('key', 'masjidProfile')
            ->update(
                ['value' => $this->profile],
                ['updated_at' => now()]
            );

        $this->dispatch('toast', type: 'success', message: 'Profile Masjid berhasil disimpan.');
        event(new DisplayUpdatesEvent('profileMasjidUpdated', $this->profile));
    }

    public function getCity()
    {
        $this->dispatch('openModalKota'); //mentrigger modal di halaman cities
    }

    #[On('citySelected')]
    public function handleCitySelected($id, $name)
    {
        $this->profile['kota'] = $id;
        $this->labelKota = $name;
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
        <flux:heading size="xl">Pengaturan Profil Masjid</flux:heading>
    </div>
    <div class="w-full lg:w-1/2">
        <form wire:submit.prevent="setupMasjid">
            <div class="mb-3">
                <flux:label class="text-sm">Nama Masjid</flux:label>
                <flux:input wire:model="profile.nama_masjid" autocomplete="off" />
            </div>
            <div class="mb-3">
                <flux:label class="text-sm">Alamat</flux:label>
                <flux:input wire:model="profile.alamat" autocomplete="off" />
            </div>
            <div class="mb-3">
                <flux:label class="text-sm">Telepon</flux:label>
                <flux:input wire:model="profile.telp" autocomplete="off" />
            </div>
            <div class="mb-3">
                <flux:label class="text-sm">Kota/Kabupaten (Untuk sinkronisasi jadwal shotal)</flux:label>
                <flux:input wire:model="profile.kota" autocomplete="off" hidden/>
                <flux:input wire:model="labelKota" autocomplete="off" wire:click="getCity" placeholder="Klik untuk cari data kota" icon="magnifying-glass"/>
            </div>
            <div class="mb-3">
                <flux:label class="text-sm">Logo Masjid</flux:label>
                <flux:input type="file" wire:model="logo" autocomplete="off" />
            </div>
            @if($profile['logo'] != '')
            <div class="mb-3">
                <flux:label>Logo Masjid</flux:label>
                <div class="p-3 border border-gray-200 bg-slate-500 lg:w-1/3 w-1/2 rounded-xl flex justify-center">
                    <img src="{{ asset('/assets/images/'.$profile['logo']) }}" alt="" width="100px">
                </div>
            </div>
            @endif
            <flux:separator variant="subtle" class="mt-3" />
            <div class="mt-3 flex gap-2 justify-start">
                <flux:button type="submit" variant="primary" class="cursor-pointer">Update</flux:button>
            </div>
            <div class="mt-3">
                @error('logo') <span class="text-red-500">{{ $message }}</span> @enderror
            </div>
        </form>
    </div>

    <livewire:pages::adminpage.cities />
</div>
