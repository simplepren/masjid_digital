<?php

use Livewire\Component;
use App\Events\DisplayUpdatesEvent;
use Illuminate\Support\Facades\DB;

new class extends Component
{
    public array $hijriOffset = [];

    public function mount()
    {
        $offset = DB::table('settings')->where('key', 'hijri_offset')->first();
        if($offset) {
            $this->hijriOffset = json_decode($offset->value, true);
        }   
    }

    public function setupHijri()
    {
        $this->validate([
            'hijriOffset.offset' => 'required',
        ]);
        // Update DB
        DB::table('settings')
            ->where('key', 'hijri_offset')
            ->update(
                ['value' => $this->hijriOffset],
                ['updated_at' => now()]
            );

        $this->dispatch('toast', type: 'success', message: 'Pengaturan Hijriyah berhasil disimpan.');
        event(new DisplayUpdatesEvent('hijriUpdated', []));
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
        <flux:heading size="xl">Pengaturan Hijriyah</flux:heading>
    </div>
    <div class="w-full lg:w-1/3">
        <form wire:submit.prevent="setupHijri">
            <div class="mb-3">
                <flux:label class="text-sm">Offset/Koreksi Hijriyah</flux:label>
                <flux:input type="number" wire:model="hijriOffset.offset" autocomplete="off" />
            </div>
            <flux:separator variant="subtle" class="mt-3" />
            <div class="mt-3 flex gap-2 justify-start">
                <flux:button type="submit" variant="primary" class="cursor-pointer">Update</flux:button>
            </div>
        </form>
    </div>
</div>
