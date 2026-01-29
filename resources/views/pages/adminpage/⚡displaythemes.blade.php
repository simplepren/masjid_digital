<?php

use Livewire\Component;
use Illuminate\Support\Facades\DB;
use App\Events\DisplayUpdatesEvent;

new class extends Component
{
    public string $template = 'template-one';
    public string $cursor = 'cursor-pointer';

    public function updatedTemplate($value)
    {
        $this->template = $value;
        DB::table('settings')->updateOrInsert(
            ['key' => 'display_template'],
            [
                'value' => json_encode(['default' => $this->template]), 
                'updated_at' => now()
            ]
        );

        $this->dispatch('toast', type: 'success', message: 'Template berhasil diubah.');
        DisplayUpdatesEvent::dispatch('reloadDisplay', []);
    }

    public function launchDisplay()
    {
        $this->dispatch('lauch-display');
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
        <flux:heading size="xl">Template Display</flux:heading>
        <flux:subheading>Pilih template tampilan yang ingin digunakan</flux:subheading>
    </div>
    <div class="mb-4 grid grid-cols-4 gap-4 p-4 max-h-[60vh] overflow-y-auto">
        <!-- TEMPLATE ONE -->
        <label class="group cursor-{{ $template == 'template-one' ? 'default' : 'pointer' }}">
            <input type="radio" name="template" value="template-one" class="peer hidden" wire:model.live="template"/>
            <div class="relative overflow-hidden rounded bg-black shadow-xl ring-4 ring-transparent 
                    peer-checked:ring-teal-500 peer-checked:scale-[1.02] 
                    transition-all duration-200"
            >
                <!-- Check Icon -->
                <div class="absolute right-2 top-2 z-10 hidden rounded-full bg-green-500 p-1 text-white peer-checked:block">
                    ✓
                </div>
                <div class="aspect-video">
                    <img src="{{ asset('assets/images/templates/template-one.jpg') }}" class="h-full w-full object-cover"/>
                </div>
                <div class="bg-slate-600 px-3 py-2 text-center text-sm text-white">
                    Template One
                </div>
            </div>
        </label>

        <!-- TEMPLATE TWO -->
        <label class="group cursor-{{ $template == 'template-two' ? 'default' : 'pointer' }}">
            <input type="radio" name="template" value="template-two" class="peer hidden" wire:model.live="template"/>
            <div class="relative overflow-hidden rounded bg-black shadow-xl ring-4 ring-transparent 
                    peer-checked:ring-teal-500 peer-checked:scale-[1.02] 
                    transition-all duration-200"
            >
                <div class="absolute right-2 top-2 z-10 hidden rounded-full bg-green-500 p-1 text-white peer-checked:block">
                    ✓
                </div>
                <div class="aspect-video">
                    <img src="{{ asset('assets/images/templates/template-two.jpg') }}" class="h-full w-full object-cover"/>
                </div>
                <div class="bg-slate-600 px-3 py-2 text-center text-sm text-white">
                    Template Two
                </div>
            </div>
        </label>
    </div>
    <div class="mt-4">
        <flux:button class="cursor-pointer" size="sm" variant="primary" wire:click="launchDisplay">Launch Display</flux:button>
    </div>
</div>

<script>
    Livewire.on('lauch-display', () => {
        window.open('/display', '_blank');
    });
</script>