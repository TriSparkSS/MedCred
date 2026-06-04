<?php

use Illuminate\Support\Facades\Route;
use App\Livewire\Authenticate\LoginPage;
use App\Livewire\Admin\DashboardPage;

Route::get('/', function () {
    return view('welcome');
});

Route::get('login', LoginPage::class)->name('login');


Route::prefix('admin')->name('admin.')->middleware(['is_auth:admin'])->group(function () {

    Route::get('dashboard', DashboardPage::class)->name('dashboard');
});
