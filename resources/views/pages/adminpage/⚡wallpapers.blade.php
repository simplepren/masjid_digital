<?php

use Livewire\Component;
use Livewire\WithFileUploads;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Computed;
use App\Events\DisplayUpdatesEvent;

new class extends Component
{
    use WithFileUploads;

    public array $wallpaperImages = [];
    public array $wallpaperDurasi = [];
    public $image;
    public $imageItem;

    public function render()
    {
        return $this->view([
            'wallpaperImages' => $this->getWallpapers(),
            'wallpaperDurasi' => $this->getDurasiWallpaper(),
        ]);
    }

    public function getWallpapers()
    {
        $wallpaper = DB::table('wallpapers')->where('key', 'wallpaper_images')->first();
        if($wallpaper) {
            $wallpaper = json_decode($wallpaper->value, true);
            $this->wallpaperImages = $wallpaper;
        }
        return $wallpaper;
    }

    public function getDurasiWallpaper()
    {
        $durasi = DB::table('wallpapers')->where('key', 'wallpaper_durasi')->first();
        if($durasi) {
            $durasi = json_decode($durasi->value, true);
            $this->wallpaperDurasi = $durasi;
        }
        return $durasi;
    }

    public function uploadImages()
    {
        // dd($this->image);
        $this->validate([
            'image' => 'required|image|max:2048',
        ]);

        if (!$this->image) {
            return;
        }

        // 1. Generate nama file
        $imageName = uniqid() . '&' . now()->timestamp . '.' . $this->image->getClientOriginalExtension();

        // 2. Simpan file
        $this->image->storeAs(
            'assets/images/wallpaper',
            $imageName,
            'real_public'
        );

        // 3. Ambil data lama
        $currentImages = $this->wallpaperImages['images'] ?? [];

        // 4. Push image baru
        $currentImages[] = $imageName;

        // 5. Update DB (TANPA json_encode manual)
        DB::table('wallpapers')
            ->where('key', 'wallpaper_images')
            ->update([
                'value' => [
                    'images' => $currentImages,
                ],
                'updated_at' => now(),
            ]);

        // 6. Reset & feedback
        $this->reset('image');
        $this->closeModal();

        $this->dispatch('toast', type: 'success', message: 'Gambar wallpaper berhasil diupload.');
        // event(new DisplayUpdatesEvent('wallpaperUpdated', [
        //     'wallpaperImages' => $this->wallpaperImages,
        //     'wallpaperDurasi' => $this->wallpaperDurasi,
        // ]));
        DisplayUpdatesEvent::dispatch('reloadDisplay', []);
    }

    public function setDurasi()
    {
        $this->validate([
            'wallpaperDurasi.durasi' => 'required|numeric'
        ]);

        DB::table('wallpapers')->where('key', 'wallpaper_durasi')->update([
            'value' => $this->wallpaperDurasi,
            'updated_at' => now()
        ]);
        $this->closeModal();
        $this->dispatch('toast', type: 'success', message: 'Durasi wallpaper berhasil disimpan.');
        // event(new DisplayUpdatesEvent('wallpaperUpdated', [
        //     'wallpaperImages' => $this->wallpaperImages,
        //     'wallpaperDurasi' => $this->wallpaperDurasi,
        // ]));
        DisplayUpdatesEvent::dispatch('reloadDisplay', []);
    }

    public function tambah()
    {
        $this->dispatch('open-modal', name: 'modal_wallpaper');
    }

    public function delete($item)
    {
        $this->imageItem = $item;
        $this->dispatch('open-modal', name: 'deleteImage');
    }

    public function formDeleteImage()
    {
        $this->validate([
            'imageItem' => 'required|string',
        ]);

        // Ambil images lama
        $images = collect($this->wallpaperImages['images'] ?? [])
            ->reject(fn ($img) => $img === $this->imageItem)
            ->values() // reset index
            ->toArray();

        // Update DB
        DB::table('wallpapers')
            ->where('key', 'wallpaper_images')
            ->update([
                'value' => [
                    'images' => $images,
                ],
                'updated_at' => now(),
            ]);
        // hapus dari storage
        Storage::disk('real_public')->delete('assets/images/wallpaper/' . $this->imageItem);
            
        // Sinkronkan state Livewire
        $this->wallpaperImages['images'] = $images;
        $this->closeModalDelete();
        $this->dispatch('toast', type: 'success', message: 'Gambar wallpaper berhasil dihapus.');
        // event(new DisplayUpdatesEvent('wallpaperUpdated', [
        //     'wallpaperImages' => $this->wallpaperImages,
        //     'wallpaperDurasi' => $this->wallpaperDurasi,
        // ]));
        DisplayUpdatesEvent::dispatch('reloadDisplay', []);
    }

    public function closeModal()
    {
        $this->dispatch('close-modal', name: 'modal_wallpaper');
        $this->reset('image');
    }

    public function closeModalDelete()
    {
        $this->dispatch('close-modal', name: 'deleteImage');
        $this->reset('imageItem');
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
        <flux:heading size="xl">Wallpaper Display</flux:heading>
    </div>
    <div class="flex items-center justify-start gap-2 mb-4">
        <flux:button size="sm" variant="primary" wire:click="tambah" class="cursor-pointer">Tambah</flux:button>
    </div>
    <div class="mb-4 grid grid-cols-4 gap-4 border border-gray-300 p-4 rounded-xl max-h-[60vh] overflow-y-auto">
        @if(!empty($wallpaperImages['images']))
            @forelse ($wallpaperImages['images'] as $item)
                <div class="group relative overflow-hidden rounded bg-black shadow-xl ring-1 ring-black/10">
                    <div class="aspect-video">
                        <img src="{{ asset('assets/images/wallpaper/'.$item) }}" class="h-full w-full object-cover transition-transform duration-500 group-hover:scale-105" />
                    </div>
                    <div class="pointer-events-none absolute inset-0 bg-linear-to-t from-black/70 via-black/30 to-transparent opacity-0 transition-opacity duration-300 group-hover:opacity-100"></div>
                    <div class="absolute bottom-0 left-0 right-0 flex items-center justify-between px-5 py-4 text-white opacity-0 translate-y-3 transition-all duration-300 group-hover:opacity-100 group-hover:translate-y-0">
                        <flux:button size="xs" wire:click="delete('{{ $item }}')" variant="primary" color="red" icon="trash" class="shadow-md hover:scale-105 transition cursor-pointer"/>
                    </div>
                </div>
            @empty
                <div class="col-span-4 text-center text-gray-500">Belum ada wallpaper</div>
            @endforelse
        @else
            <div class="col-span-4 text-center text-gray-500">Belum ada wallpaper</div>
        @endif
    </div>
    <div class="w-full lg:w-1/3">
        <form wire:submit.prevent="setDurasi">
            <div class="flex justify-between items-center gap-3">
                <flux:label>Durasi</flux:label>
                <flux:select wire:model="wallpaperDurasi.durasi">
                    <flux:select.option value="300">5 Menit</flux:select.option>
                    <flux:select.option value="600">10 Menit</flux:select.option>
                    <flux:select.option value="900">15 Menit</flux:select.option>
                    <flux:select.option value="1800">30 Menit</flux:select.option>
                    <flux:select.option value="3600">1 Jam</flux:select.option>
                    <flux:select.option value="7200">2 Jam</flux:select.option>
                    <flux:select.option value="10800">3 Jam</flux:select.option>
                </flux:select>
                <flux:tooltip content="Simpan">
                    <flux:button type="submit" icon="check" size="sm" variant="primary" color="emerald" class="cursor-pointer" />
                </flux:tooltip>
            </div>
        </form>
    </div>

    <x-custom-modal name="modal_wallpaper" width="w-2xl" :dismissible="false" :closable="false">
        <div class="mb-3">
            <h4 class="font-semibold text-lg">Upload Wallpaper</h4>
            <flux:text>Upload wallpaper untuk background display masjid</flux:text>
        </div>
        <form wire:submit.prevent="uploadImages">
            <div class="mb-3">
                <flux:label class="text-sm">Image</flux:label>
                <flux:input wire:model="image" type="file" accept="image/*" />
                @error('image') <span class="text-xs text-pink-600">{{ $message }}</span> @enderror
            </div>
            <div wire:loading wire:target="image" class="text-xs italic text-gray-500 mb-3">
                Memuat preview...
            </div>
            <div class="mb-5">
                @if($image)
                    <flux:label class="text-sm">Preview</flux:label>
                    <div>
                        <img src="{{ $image->temporaryUrl() }}" alt="" width="200px">
                    </div>
                @endif
            </div>
            <div class="mb-3">
                <span class="text-xs text-pink-600">* Pastikan rasio gambar 16:9, ukuran maks 2MB</span>
            </div>
            <flux:separator variant="subtle" class="mt-3" />
            <div class="mt-3 flex gap-2 justify-end">
                <flux:button wire:click="closeModal" variant="primary" color="amber" class="cursor-pointer">Close</flux:button>
                <flux:button type="submit" variant="primary" class="cursor-pointer">Simpan</flux:button>
            </div>
        </form>
    </x-custom-modal>

    <x-custom-modal name="deleteImage" width="w-md" :dismissible="false" :closable="false">
        <div class="space-y-6">
            <form wire:submit.prevent="formDeleteImage">
                <div>
                    <flux:heading size="lg">Hapus image</flux:heading>
                    <flux:input wire:model="imageItem" hidden/>
                    <flux:text class="mt-2">
                        Yakin mau hapus image ini?<br>
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
</div>
