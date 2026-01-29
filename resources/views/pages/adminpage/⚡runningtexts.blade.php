<?php

use Livewire\Component;
use Livewire\WithPagination;
use App\Events\DisplayUpdatesEvent;
use Illuminate\Support\Facades\DB;
use Livewire\WithoutUrlPagination;
use Livewire\Attributes\Computed;

new class extends Component
{
    use WithPagination, WithoutUrlPagination;

    public $perPage = 10;
    public $id_text;
    public $text;
    public $orderIndex;
    public $isActive = true;

    public $editMode = false;
    public $judulModal = 'Tambah Running Text';

    #[Computed()]
    public function getRunningTexts()
    {
        $texts = DB::table('running_texts')
            ->orderBy('active', 'desc')
            ->orderBy('order_index', 'asc')
            ->orderBy('created_at', 'desc')
            ->simplePaginate($this->perPage);
        
        return $texts;
    }

    public function tambah()
    {
        $this->dispatch('open-modal', name: 'modal_runningtext');
    }

    public function edit($id)
    {
        $text = DB::table('running_texts')->where('id', $id)->first();
        $this->id_text = $text->id;
        $this->text = $text->text;
        $this->isActive = $text->active;
        $this->editMode = true;
        $this->judulModal = 'Edit Running Text';
        $this->dispatch('open-modal', name: 'modal_runningtext');
    }

    public function delete($id)
    {
        $this->id_text = $id;
        $this->dispatch('open-modal', name: 'deleteText');
    }

    public function closeModalDelete()
    {
        $this->dispatch('close-modal', name: 'deleteText');
        $this->reset('id_text');
    }

    public function formDeleteText()
    {
        DB::table('running_texts')->where('id', $this->id_text)->delete();
        $this->dispatch('toast', type: 'success', message: 'Running Text berhasil dihapus.');
        event(new DisplayUpdatesEvent('runningTextUpdated', []));
        $this->closeModalDelete();
    }

    public function getOrderIndex()
    {
        $orderIndex = DB::table('running_texts')->max('order_index');
        return $orderIndex + 1;
    }

    public function setupRunningText()
    {
        $this->validate([
            'text' => 'required',
            'isActive' => 'required'
        ]);

        if($this->editMode) {
            DB::table('running_texts')->where('id', $this->id_text)->update([
                'text' => $this->text,
                'active' => $this->isActive,
                'updated_at' => now()
            ]);
        } else {
            DB::table('running_texts')->insert([
                'text' => $this->text,
                'order_index' => $this->getOrderIndex(),
                'active' => $this->isActive,
                'created_at' => now(),
                'updated_at' => now()
            ]);
        }
        $this->dispatch('toast', type: 'success', message: 'Running Text berhasil disimpan.');
        event(new DisplayUpdatesEvent('runningTextUpdated', []));
        $this->closeModal();
    }

    public function closeModal()
    {
        $this->dispatch('close-modal', name: 'modal_runningtext');
        $this->reset('id_text','text', 'orderIndex', 'isActive', 'editMode', 'judulModal');
    }

    public function up($id)
    {
        DB::transaction(function () use ($id) {

            $ids = DB::table('running_texts')
                ->orderBy('order_index')
                ->lockForUpdate()
                ->pluck('id')
                ->values();

            $pos = $ids->search($id);

            // sudah paling atas atau tidak ditemukan
            if ($pos === false || $pos === 0) return;

            // swap posisi
            [$ids[$pos - 1], $ids[$pos]] = [$ids[$pos], $ids[$pos - 1]];

            $this->reorderIndex($ids);
        });
    }

    public function down($id)
    {
        DB::transaction(function () use ($id) {

            $ids = DB::table('running_texts')
                ->orderBy('order_index')
                ->lockForUpdate()
                ->pluck('id')
                ->values();

            $pos = $ids->search($id);

            // sudah paling bawah atau tidak ditemukan
            if ($pos === false || $pos === $ids->count() - 1) return;

            // swap posisi
            [$ids[$pos], $ids[$pos + 1]] = [$ids[$pos + 1], $ids[$pos]];

            $this->reorderIndex($ids);
        });
    }

    private function reorderIndex($ids)
    {
        foreach ($ids as $index => $id) {
            DB::table('running_texts')
                ->where('id', $id)
                ->update([
                    'order_index' => $index + 1
                ]);
        }
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
        <flux:heading size="xl">Setup Running Text</flux:heading>
    </div>
    <div class="flex items-center justify-start gap-2 mb-2">
        <flux:button size="sm" variant="primary" wire:click="tambah" class="cursor-pointer">Tambah</flux:button>
    </div>
    <div class="overflow-x-auto border rounded-lg">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-teal-600 text-white font-semibold text-sm">
                <tr>
                    <th class="px-4 py-3 text-left uppercase tracking-wider">Text</th>
                    <th class="px-4 py-3 text-left uppercase tracking-wider">Status</th>
                    <th class="px-4 py-3 text-left uppercase tracking-wider">Order</th>
                    <th class="px-4 py-3 text-left uppercase tracking-wider">Action</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                @forelse ($this->getRunningTexts as $item)
                    <tr class="hover:bg-gray-50 transition">
                        <td class="px-4 py-3 text-sm text-gray-600">
                            {{ $item->text }}
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-600">
                            @if($item->active == 1)
                                <flux:badge size="sm" color="emerald">Aktif</flux:badge>
                            @else
                                <flux:badge size="sm" color="amber">Tidak Aktif</flux:badge>
                            @endif
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-600">
                            {{ $item->order_index }}
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-600">
                            @if($item->order_index != 1)
                                <flux:tooltip content="Naik">
                                    <flux:button size="sm" wire:click="up({{ $item->id }})" variant="primary" color="emerald" class="cursor-pointer" icon="square-arrow-up"></flux:button>
                                </flux:tooltip>
                            @endif
                            @if($item->order_index != count($this->getRunningTexts))
                                <flux:tooltip content="Turun">
                                    <flux:button size="sm" wire:click="down({{ $item->id }})" variant="primary" color="orange" class="cursor-pointer" icon="square-arrow-down"></flux:button>
                                </flux:tooltip>
                            @endif
                            <flux:tooltip content="Edit">
                                <flux:button size="sm" wire:click="edit({{ $item->id }})" variant="primary" color="zinc" class="cursor-pointer" icon="pencil"></flux:button>
                            </flux:tooltip>
                            <flux:modal.trigger name="deleteText" wire:click="delete({{ $item->id }})">
                                <flux:tooltip content="Hapus">
                                    <flux:button size="sm" variant="danger" icon="trash" class="cursor-pointer"></flux:button>
                                </flux:tooltip>
                            </flux:modal.trigger>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-4 py-10 text-center text-gray-500">
                            Data running text belum tersedia.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-2">{{ $this->getRunningTexts->links() }}</div>

    <x-custom-modal name="deleteText" width="w-md" :dismissible="false" :closable="false">
        <div class="space-y-6">
            <form wire:submit.prevent="formDeleteText">
                <div>
                    <flux:heading size="lg">Hapus running text</flux:heading>
                    <flux:input wire:model="id_text" hidden/>
                    <flux:text class="mt-2">
                        Yakin mau hapus running text ini?<br>
                        Tindakan ini tidak bisa dibatalkan
                    </flux:text>
                </div>
                <div class="flex gap-2">
                    <flux:spacer />
                    <flux:button wire:click="closeModalDelete" variant="primary" color="amber" class="cursor-pointer">Batal</flux:button>
                    <flux:button type="submit" variant="danger" class="cursor-pointer">Hapus</flux:button>
                </div>
            </div>
        </form>
    </x-custom-modal>

    <x-custom-modal name="modal_runningtext" width="w-2xl" :dismissible="false" :closable="false">
        <div class="mb-3">
            <h4 class="font-semibold text-lg">{{ $judulModal }}</h4>
            <flux:text>Anda dapat menambahkan/mengubah running text dengan mengisi form berikut</flux:text>
        </div>
        <form wire:submit.prevent="setupRunningText">
            <div class="mb-3">
                <flux:label class="text-sm">Text</flux:label>
                <flux:textarea wire:model="text"></flux:textarea>
            </div>
            <div class="mb-3">
                <flux:label class="text-sm">Status</flux:label>
                <flux:select wire:model="isActive">
                    <flux:select.option value="1">Aktif</flux:select.option>
                    <flux:select.option value="0">Tidak Aktif</flux:select.option>
                </flux:select>
            </div>
            <flux:separator variant="subtle" class="mt-3" />
            <div class="mt-3 flex gap-2 justify-end">
                <flux:button wire:click="closeModal" variant="primary" color="amber" class="cursor-pointer">Close</flux:button>
                <flux:button type="submit" variant="primary" class="cursor-pointer">Simpan</flux:button>
            </div>
        </form>
    </x-custom-modal>
</div>
