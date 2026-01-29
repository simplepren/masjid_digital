<?php

use App\Models\Setting;
use App\Events\DisplayUpdatesEvent;
use Livewire\Component;
use Illuminate\Support\Facades\DB;

new class extends Component
{
    public array $durasi_adzan = [];
    public array $durasi_iqomah = [];
    public array $durasi_sholat = [];

    public function mount()
    {
        $adzan = DB::table('settings')->where('key', 'durasi_adzan')->first();
        $iqomah = DB::table('settings')->where('key', 'durasi_iqomah')->first();
        $sholat = DB::table('settings')->where('key', 'durasi_sholat')->first();
        
        // Konversi dari DETIK ke MENIT agar mudah dibaca di Form
        if ($adzan) {
            $this->durasi_adzan = array_map(fn($v) => $v / 60, json_decode($adzan->value, true));
        }   
        if ($iqomah) {
            $this->durasi_iqomah = array_map(fn($v) => $v / 60, json_decode($iqomah->value, true));
        }
        if ($sholat) {
            $this->durasi_sholat = array_map(fn($v) => $v / 60, json_decode($sholat->value, true));
        }
    }

    public function saveSetupSholat()
    {
        // Konversi balik dari MENIT ke DETIK sebelum disimpan ke Database
        $adzan_seconds = array_map(fn($v) => (int)$v * 60, $this->durasi_adzan);
        $iqomah_seconds = array_map(fn($v) => (int)$v * 60, $this->durasi_iqomah);
        $sholat_seconds = array_map(fn($v) => (int)$v * 60, $this->durasi_sholat);

        DB::transaction(function () use ($adzan_seconds, $iqomah_seconds, $sholat_seconds) {
            DB::table('settings')->where('key', 'durasi_adzan')->update([
                'value' => $adzan_seconds,
                'updated_at' => now()
            ]);
            
            DB::table('settings')->where('key', 'durasi_iqomah')->update([
                'value' => $iqomah_seconds,
                'updated_at' => now()
            ]);
            
            DB::table('settings')->where('key', 'durasi_sholat')->update([
                'value' => $sholat_seconds,
                'updated_at' => now()
            ]);
        });

        $this->dispatch('toast', type: 'success', message: 'Pengaturan Sholat berhasil disimpan.');
        event(new DisplayUpdatesEvent('prayerSettingUpdated', []));
    }

    public function restoreToDefault()
    {
        $this->durasi_adzan = [
            'subuh' => 3,
            'dzuhur' => 3,
            'ashar' => 3,
            'maghrib' => 3,
            'isya' => 3,
        ];
        $this->durasi_iqomah = [
            'subuh' => 10,
            'dzuhur' => 10,
            'ashar' => 5,
            'maghrib' => 10,
            'isya' => 10,
        ];
        $this->durasi_sholat = [
            'subuh' => 15,
            'dzuhur' => 15,
            'ashar' => 15,
            'maghrib' => 15,
            'isya' => 15,
            'jumat' => 40
        ];
        $this->saveSetupSholat();
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
        <flux:heading size="xl">Pengaturan Sholat</flux:heading>
    </div>
    <div class="w-full">
        <form wire:submit.prevent="saveSetupSholat">
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
                <div>
                    <div class="mb-2">
                        <flux:heading size="lg">Durasi Adzan (Menit)</flux:heading>
                    </div>
                    <div class="mb-3">
                        <flux:label class="text-sm">Subuh</flux:label>
                        <flux:input wire:model="durasi_adzan.subuh" type="number" min="0" autocomplete="off" />
                    </div>
                    <div class="mb-3">
                        <flux:label class="text-sm">Dzuhur</flux:label>
                        <flux:input wire:model="durasi_adzan.dzuhur" type="number" min="0" autocomplete="off" />
                    </div>
                    <div class="mb-3">
                        <flux:label class="text-sm">Ashar</flux:label>
                        <flux:input wire:model="durasi_adzan.ashar" type="number" min="0" autocomplete="off" />
                    </div>
                    <div class="mb-3">
                        <flux:label class="text-sm">Maghrib</flux:label>
                        <flux:input wire:model="durasi_adzan.maghrib" type="number" min="0" autocomplete="off" />
                    </div>
                    <div class="mb-3">
                        <flux:label class="text-sm">Isya</flux:label>
                        <flux:input wire:model="durasi_adzan.isya" type="number" min="0" autocomplete="off" />
                    </div>
                </div>
                <div>
                    <div class="mb-2">
                        <flux:heading size="lg">Durasi Iqomah (Menit)</flux:heading>
                    </div>
                    <div class="mb-3">
                        <flux:label class="text-sm">Subuh</flux:label>
                        <flux:input wire:model="durasi_iqomah.subuh" type="number" min="0" autocomplete="off" />
                    </div>
                    <div class="mb-3">
                        <flux:label class="text-sm">Dzuhur</flux:label>
                        <flux:input wire:model="durasi_iqomah.dzuhur" type="number" min="0" autocomplete="off" />
                    </div>
                    <div class="mb-3">
                        <flux:label class="text-sm">Ashar</flux:label>
                        <flux:input wire:model="durasi_iqomah.ashar" type="number" min="0" autocomplete="off" />
                    </div>
                    <div class="mb-3">
                        <flux:label class="text-sm">Maghrib</flux:label>
                        <flux:input wire:model="durasi_iqomah.maghrib" type="number" min="0" autocomplete="off" />
                    </div>
                    <div class="mb-3">
                        <flux:label class="text-sm">Isya</flux:label>
                        <flux:input wire:model="durasi_iqomah.isya" type="number" min="0" autocomplete="off" />
                    </div>
                </div>
                <div>
                    <div class="mb-2">
                        <flux:heading size="lg">Durasi Sholat (Menit)</flux:heading>
                    </div>
                    <div class="mb-3">
                        <flux:label class="text-sm">Subuh</flux:label>
                        <flux:input wire:model="durasi_sholat.subuh" type="number" min="0" autocomplete="off" />
                    </div>
                    <div class="mb-3">
                        <flux:label class="text-sm">Dzuhur</flux:label>
                        <flux:input wire:model="durasi_sholat.dzuhur" type="number" min="0" autocomplete="off" />
                    </div>
                    <div class="mb-3">
                        <flux:label class="text-sm">Jumat</flux:label>
                        <flux:input wire:model="durasi_sholat.jumat" type="number" min="0" autocomplete="off" />
                    </div>
                    <div class="mb-3">
                        <flux:label class="text-sm">Ashar</flux:label>
                        <flux:input wire:model="durasi_sholat.ashar" type="number" min="0" autocomplete="off" />
                    </div>
                    <div class="mb-3">
                        <flux:label class="text-sm">Maghrib</flux:label>
                        <flux:input wire:model="durasi_sholat.maghrib" type="number" min="0" autocomplete="off" />
                    </div>
                    <div class="mb-3">
                        <flux:label class="text-sm">Isya</flux:label>
                        <flux:input wire:model="durasi_sholat.isya" type="number" min="0" autocomplete="off" />
                    </div>
                </div>
            </div>
            <flux:separator variant="subtle" class="mt-3" />
            <div class="mt-3 flex gap-2 justify-start">
                <flux:button variant="primary" wire:click="restoreToDefault" color="amber" class="cursor-pointer">Reset</flux:button>
                <flux:button type="submit" variant="primary" class="cursor-pointer">Update</flux:button>
            </div>
        </form>
    </div>
</div>
