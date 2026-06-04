<?php

use Illuminate\Support\Facades\Route;
use App\Livewire\Authenticate\LoginPage;
use App\Livewire\Admin\DashboardPage;
use App\Livewire\Admin\Provider\ProviderListPage;
use App\Livewire\Admin\Practices\PracticeListPage;
use App\Livewire\Admin\Credential\CredentialListPage;

Route::get('/', function () {
    return view('welcome');
});

Route::get('sp/login', LoginPage::class)->name('login');


Route::prefix('admin')->name('admin.')->middleware(['is_auth:admin'])->group(function () {

    Route::get('dashboard', DashboardPage::class)->name('dashboard');
    Route::get('providers', ProviderListPage::class)->name('providers');
    Route::get('practices', PracticeListPage::class)->name('practices');
    Route::get('credentials', CredentialListPage::class)->name('credentials');


});
