<?php

use App\Models\User;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Computed;
use Livewire\WithoutUrlPagination;

new class extends Component
{
    use WithPagination, WithoutUrlPagination;

    public int $perPage = 10;
    public string $search = '';

    public int $id_user;
    public string $name = '';
    public string $email = '';
    public string $old_password = '';
    public string $password = '';

    public bool $editMode = false;
    public string $modalTitle = 'Tambah User';
    public string $placeholderPassword = 'Masukkan password minimal 6 karakter';

    #[Computed()]
    public function getUsers()
    {
        return User::where('name', 'like', '%' . $this->search . '%')
            ->orWhere('email', 'like', '%' . $this->search . '%')
            ->orderBy('name', 'asc')
            ->paginate($this->perPage);
    }

    public function tambah()
    {
        $this->dispatch('open-modal', name: 'modal_user');
    }

    public function editUser($userId)
    {
        $user = User::findOrFail($userId);
        $this->id_user = $user->id;
        $this->name = $user->name;
        $this->email = $user->email;
        $this->old_password = $user->password;
        $this->editMode = true;
        $this->modalTitle = 'Edit User';
        $this->placeholderPassword = 'Kosongkan jika tidak ingin mengubah password';
        $this->dispatch('open-modal', name: 'modal_user');
    }

    public function deleteUser($userId)
    {
        $user = User::findOrFail($userId);
        $this->id_user = $user->id;
        $this->dispatch('open-modal', name: 'modal_delete_user');
    }

    public function formUsers()
    {
        $this->validate([
            'name' => 'required',
            'email' => 'required|email',
            'password' => $this->editMode ? 'nullable|min:6' : 'required|min:6',
        ]);

        if ($this->editMode) {
            $user = User::findOrFail($this->id_user);
            $user->update([
                'name' => $this->name,
                'email' => $this->email,
                'password' => $this->password ?? $this->old_password,
            ]);
        } else {
            User::create([
                'name' => $this->name,
                'email' => $this->email,
                'password' => $this->password,
            ]);
        }

        $this->dispatch('toast', type: 'success', message: 'Data user berhasil disimpan.');
        $this->closeModalUser();
    }

    public function formDeleteUser()
    {
        //cek apakah user sedang login
        if (auth()->user()->id == $this->id_user) {
            $this->dispatch('toast', type: 'error', message: 'Tidak bisa menghapus user yang sedang login.');
            $this->closeModalDelete();
            return;
        }

        User::findOrFail($this->id_user)->delete();
        $this->dispatch('toast', type: 'success', message: 'Data user berhasil dihapus.');
        $this->closeModalDelete();
    }

    public function closeModalDelete()
    {
        $this->reset('id_user');
        $this->dispatch('close-modal', name: 'modal_delete_user');
    }

    public function closeModalUser()
    {
        $this->reset('id_user', 'name', 'email', 'password', 'editMode', 'modalTitle');
        $this->dispatch('close-modal', name: 'modal_user');
    }

    public function searchUsers()
    {
        $this->resetPage();
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
        <flux:heading size="xl">Setup User</flux:heading>
    </div>
    <div class="mb-3 flex items-center justify-start gap-2">
        <flux:button size="sm" variant="primary" wire:click="tambah" class="cursor-pointer">Tambah</flux:button>
    </div>
    <div class="mb-3 w-full lg:w-1/3">
        <form wire:submit.prevent="searchUsers">
            <flux:input type="search" placeholder="Cari user.." wire:model="search" autocomplete="off" />
        </form>
    </div>
    <div class="overflow-x-auto border rounded-lg">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-teal-600 text-white font-semibold text-sm">
                <tr>
                    <th class="px-4 py-3 text-left uppercase tracking-wider">No.</th>
                    <th class="px-4 py-3 text-left uppercase tracking-wider">Nama Lengkap</th>
                    <th class="px-4 py-3 text-left uppercase tracking-wider">Email</th>
                    <th class="px-4 py-3 text-left uppercase tracking-wider">Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($this->getUsers as $user)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-900">{{ $loop->iteration }}</td>
                        <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-900">{{ $user->name }}</td>
                        <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-900">{{ $user->email }}</td>
                        <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-900">
                            <flux:tooltip content="Edit User">
                                <flux:button class="cursor-pointer" size="sm" variant="primary" color="amber" icon="pencil" wire:click="editUser({{ $user->id }})" />
                            </flux:tooltip>
                            <flux:tooltip content="Hapus User">
                                <flux:button class="cursor-pointer" size="sm" variant="danger" icon="trash" wire:click="deleteUser({{ $user->id }})" />
                            </flux:tooltip>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-600 text-center">
                            Tidak ada data user.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        <div class="mt-4 flex justify-between">
            {{ $this->getUsers->links() }}
        </div>
    </div>

    <x-custom-modal name="modal_delete_user" width="w-md" :dismissible="false" :closable="false">
        <div class="space-y-6">
            <form wire:submit.prevent="formDeleteUser">
                <div>
                    <flux:heading size="lg">Hapus User</flux:heading>
                    <flux:input wire:model="id_user" hidden/>
                    <flux:text class="mt-2">
                        Yakin mau hapus user ini?<br>
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

    <x-custom-modal name="modal_user" width="w-2xl" :dismissible="false" :closable="false">
        <div class="mb-3">
            <h4 class="font-semibold text-lg">{{ $modalTitle }}</h4>
        </div>
        <form wire:submit.prevent="formUsers">
            <input type="hidden" wire:model="id_user">
            <div class="mb-3">
                <flux:label class="text-sm">Nama Lengkap</flux:label>
                <flux:input wire:model="name"/>
            </div>
            <div class="mb-3">
                <flux:label class="text-sm">Email</flux:label>
                <flux:input wire:model="email"/>
            </div>
            <div class="mb-3">
                <flux:label class="text-sm">Password</flux:label>
                <flux:input wire:model="password" type="password" :placeholder="$placeholderPassword"/>
            </div>
            <flux:separator variant="subtle" class="mt-3" />
            <div class="mt-3 flex gap-2 justify-end">
                <flux:button wire:click="closeModalUser" variant="primary" color="amber" class="cursor-pointer">Close</flux:button>
                <flux:button type="submit" variant="primary" class="cursor-pointer">Simpan</flux:button>
            </div>
        </form>
    </x-custom-modal>
</div>