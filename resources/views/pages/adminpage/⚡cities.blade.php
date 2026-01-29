<?php

use App\Models\City;
use Livewire\Component;
use Livewire\Attributes\On;
use Livewire\Attributes\Computed;
use Livewire\WithPagination;
use Livewire\WithoutUrlPagination;

new class extends Component
{
    use WithPagination, WithoutUrlPagination;

    public $searchKota = '';
    public $perPage = 10;

    #[Computed()]
    public function getCities()
    {
        return City::where('lokasi', 'like', "%{$this->searchKota}%")->simplePaginate($this->perPage);
    }

    public function formSearchKota()
    {
        $this->resetPage();
        $this->reset('perPage');
    }

    #[On('openModalKota')]
    public function handleOpenModalKota()
    {
        $this->dispatch('open-modal', name: 'modal_kota');
    }

    public function closeModal()
    {
        $this->dispatch('close-modal', name: 'modal_kota');
        $this->reset('searchKota');
    }

    public function selectCity($cityId, $cityName)
    {    
        $this->dispatch('citySelected', id: $cityId, name: $cityName); //kirim data ke syncronize 
        $this->closeModal();
    }
};
?>

<x-custom-modal name="modal_kota" width="w-3xl" :dismissible="false" :closable="false">
    <div class="p-2 max-h-[90vh] overflow-y-auto">
        <!--loader-->
        <div wire:loading class="fixed left-0 top-0 bg-white opacity-70 text-center w-full h-full" style="z-index: 51">
            <div class="min-h-full flex items-center justify-center">
                <div role="status">
                    <div class="page-loader"></div>
                </div>
            </div>
        </div>
        <!--loader-->

        <div class="mb-5">
            <h4 class="font-semibold text-lg">Daftar Kota</h4>
            <flux:text>Silakan pilih kota/kabupaten</flux:text>
        </div>
        <div class="mb-3 lg:flex lg:gap-3 lg:space-y-0 space-y-3">
            <div class="w-full">
                <form action="" wire:submit="formSearchKota">
                    <flux:input wire:model="searchKota" icon="magnifying-glass" placeholder="Search..." autocomplete="off"/>
                </form>
            </div>
        </div>
        <div class="mb-3">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-teal-600 text-white font-semibold text-sm">
                    <tr>
                        <th class="px-4 py-3 text-left uppercase tracking-wider">ID</th>
                        <th class="px-4 py-3 text-left uppercase tracking-wider">Nama Kota/Kab</th>
                        <th class="px-4 py-3 text-left uppercase w-32">Action</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($this->getCities as $city)
                    <tr class="hover:bg-gray-50 transition">
                        <td class="px-4 py-1 whitespace-nowrap text-sm text-gray-900">
                            {{ $city->lokasi_id }}
                        </td>
                        <td class="px-4 py-1 whitespace-nowrap text-sm text-gray-900">
                            {{ $city->lokasi }}
                        </td>
                        <td class="px-4 py-1 whitespace-nowrap text-sm text-gray-900">
                            <flux:button size="sm" wire:click="selectCity({{ $city->lokasi_id }}, '{{ $city->lokasi }}')" variant="primary" color="zinc" class="cursor-pointer">Pilih</flux:button>
                        </td>
                    </tr>
                    @empty
                        <td colspan="3" class="px-4 py-10 text-center text-gray-500">
                            Kota tidak ditemukan <br>
                        </td>
                    @endforelse
                </tbody>
            </table>
            <div class="mt-2">{{ $this->getCities->links() }}</div>
            <flux:separator variant="subtle" class="mt-3" />
            <div class="mt-3 flex ustify-end">
                <flux:button wire:click="closeModal" variant="primary" color="amber" class="cursor-pointer">Close</flux:button>
            </div>
        </div>
    </div>
</x-custom-modal>