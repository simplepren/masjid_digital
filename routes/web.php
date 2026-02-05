<?php

use Illuminate\Support\Facades\Route;
use App\Http\Middleware\VerifyCsrfToken;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

Route::middleware(['auth'])->group(function () {
    Route::livewire('/admin/dashboard', 'pages::adminpage.dashboard')->name('dashboard');
    Route::livewire('/admin/prayertime', 'pages::adminpage.prayertimes')->name('admin.prayertimes');
    Route::livewire('/admin/masjidprofile', 'pages::adminpage.masjidprofile')->name('admin.masjidprofile');
    Route::livewire('/admin/prayersetting', 'pages::adminpage.prayersetting')->name('admin.prayersetting');
    Route::livewire('/admin/runningtext', 'pages::adminpage.runningtexts')->name('admin.runningtexts');
    Route::livewire('/admin/textslider', 'pages::adminpage.textslider')->name('admin.textsliders');
    Route::livewire('/admin/hijrisetting', 'pages::adminpage.hijrisetting')->name('admin.hijrisetting');
    Route::livewire('/admin/wallpaper', 'pages::adminpage.wallpapers')->name('admin.wallpapers');
    Route::livewire('/admin/displaythemes', 'pages::adminpage.displaythemes')->name('admin.displaythemes');
    Route::livewire('/admin/users', 'pages::adminpage.users')->name('admin.users');
});

Route::livewire('display', 'pages::display.index')->name('display');

Route::livewire('/', 'pages::display.welcome')->name('home');

// Ping connection test
Route::get('/ping', fn () => response('OK', 200));


require __DIR__.'/settings.php';
