<?php

use Livewire\Component;
use App\Events\DisplayUpdatesEvent;

new class extends Component
{
    public function reload()
    {
        DisplayUpdatesEvent::dispatch('reloadDisplay', []);
    }
};
?>

<div>
    <flux:button size="sm" variant="primary" wire:click="reload" class="cursor-pointer">Reload Display</flux:button>
</div>