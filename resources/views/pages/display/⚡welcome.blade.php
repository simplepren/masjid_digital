<?php

use Livewire\Component;
use Livewire\Attributes\Layout;

new #[Layout('layouts::display')] 
class extends Component
{
    //
};
?>

<div class="w-screen h-screen bg-white flex p-6 lg:p-8 items-center lg:justify-center min-h-screen flex-col overflow-y-auto">
    <header class="w-full lg:max-w-4xl max-w-83.75 text-sm mb-6 not-has-[nav]:hidden">
        @if (Route::has('login'))
            <nav class="flex items-center justify-end gap-4">
                @auth
                    <a href="{{ url('/admin/dashboard') }}" class="inline-block px-5 py-1.5 dark:text-[#EDEDEC] border-[#19140035] hover:border-[#1915014a] border text-[#1b1b18] dark:border-[#3E3E3A] dark:hover:border-[#62605b] rounded-sm text-sm leading-normal">Dashboard</a>
                @else
                    <a href="{{ route('login') }}" class="inline-block px-5 py-1.5 dark:text-[#EDEDEC] text-[#1b1b18] border border-transparent hover:border-[#19140035] dark:hover:border-[#3E3E3A] rounded-sm text-sm leading-normal">Log in</a>
                    @if (Route::has('register'))
                        <a href="{{ route('register') }}" class="inline-block px-5 py-1.5 dark:text-[#EDEDEC] border-[#19140035] hover:border-[#1915014a] border text-[#1b1b18] dark:border-[#3E3E3A] dark:hover:border-[#62605b] rounded-sm text-sm leading-normal">Register</a>
                    @endif
                @endauth
            </nav>
        @endif
    </header>
    <div class="mt-16 flex justify-center w-full transition-opacity opacity-100 duration-750 lg:grow starting:opacity-0">
        <div class="lg:w-1/2 w-full lg:max-w-4xl">
            <div class="text-2xl font-bold text-center mb-6 lg:mb-0">Aplikasi Display Masjid</div>
            <div class="mt-3">
                <span>Aplikasi ini free/berbasis open source dan dapat digunakan oleh siapa saja. Anda bisa mengubah dan memodifikasi aplikasi ini sesuai kebutuhan Anda tanpa harus meminta izin maupun memberikan atribusi apapun. Silakan gunakan dengan bijak.</span>
            </div>
            <div class="mt-4 text-xl font-semibold">Panduan Pengguna</div>
            <div>
                <ol class="list-disc list-inside space-y-1 mt-2">
                    <li>Login melalui route /login</li>
                    <li>Lengkapi Profil Masjid</li>
                    <li>Sinkronisasi Jadwal Sholat pada menu Jadwal Sholat (API sebaiknya jangan diubah/diganti)</li>
                    <li>Atur Durasi Adzan, Iqomah, dan Sholat pada menu Pengaturan Sholat</li>
                    <li>Pilih Template Display pada menu Template Display</li>
                    <li>Silakan buka/akses tampilan display masjid (TV) melalui route /display</li>
                </ol>
            </div>
            <div class="mt-4 text-xl font-semibold">Tech Stack</div>
            <div>
                <ol class="list-disc list-inside space-y-1 mt-2">
                    <li>Laravel v.12</li>
                    <li>LiveWire v.4</li>
                    <li>Alpine JS</li>
                </ol>
            </div>
        </div>
    </div>
</div>