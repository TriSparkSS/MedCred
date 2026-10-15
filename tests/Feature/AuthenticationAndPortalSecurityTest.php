<?php

use App\Livewire\Authenticate\LoginPage;
use App\Livewire\Authenticate\PortalLoginPage;
use App\Models\Admin;
use App\Models\Practice;
use App\Models\ProviderDetails;
use App\Models\User;
use Database\Seeders\AdminPermissionSeeder;
use Database\Seeders\AdminSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function () {
    app()[PermissionRegistrar::class]->forgetCachedPermissions();
    $this->seed(AdminPermissionSeeder::class);
    $this->seed(AdminSeeder::class);
    $this->seed(RoleSeeder::class);
});

test('admin login page validates empty inputs, blocks inactive admins, and rate-limits after 5 failed attempts', function () {
    Livewire::test(LoginPage::class)
        ->set('login', '')
        ->set('password', '')
        ->call('authenticate')
        ->assertHasErrors(['login', 'password']);

    $inactiveAdmin = Admin::create([
        'name' => 'Inactive Admin',
        'username' => 'inactive_admin',
        'email' => 'inactive-admin@example.com',
        'password' => 'secret123',
        'status' => 'inactive',
    ]);

    Livewire::test(LoginPage::class)
        ->set('login', $inactiveAdmin->email)
        ->set('password', 'secret123')
        ->call('authenticate')
        ->assertHasErrors(['login']);

    expect(Auth::guard('admin')->check())->toBeFalse();

    $activeAdmin = Admin::where('username', 'superadmin')->firstOrFail();
    RateLimiter::clear('admin-login:' . strtolower($activeAdmin->username) . '|127.0.0.1');

    for ($i = 0; $i < 5; $i++) {
        Livewire::test(LoginPage::class)
            ->set('login', $activeAdmin->username)
            ->set('password', 'wrong-password')
            ->call('authenticate')
            ->assertHasErrors(['login']);
    }

    Livewire::test(LoginPage::class)
        ->set('login', $activeAdmin->username)
        ->set('password', 'password')
        ->call('authenticate')
        ->assertHasErrors(['login'])
        ->assertSee('Too many login attempts');
});

test('portal login page validates empty inputs, blocks inactive practice and provider accounts, and rate-limits after 5 failed attempts', function () {
    Livewire::test(PortalLoginPage::class)
        ->set('email', '')
        ->set('password', '')
        ->call('authenticate')
        ->assertHasErrors(['email', 'password']);

    $practiceUser = User::create([
        'name' => 'Inactive Practice User',
        'email' => 'inactive-practice@example.com',
        'password' => 'secret123',
    ]);
    $practiceUser->assignRole('practice');

    Practice::create([
        'legal_name' => 'Inactive Practice LLC',
        'client_code' => 'IPL',
        'email' => 'ipl@example.com',
        'status' => 'inactive',
        'user_id' => $practiceUser->id,
    ]);

    Livewire::test(PortalLoginPage::class)
        ->set('email', $practiceUser->email)
        ->set('password', 'secret123')
        ->call('authenticate')
        ->assertHasErrors(['email'])
        ->assertSee('Your practice account is inactive.');

    expect(Auth::guard('web')->check())->toBeFalse();

    $providerUser = User::create([
        'name' => 'Inactive Provider User',
        'email' => 'inactive-provider@example.com',
        'password' => 'secret123',
    ]);
    $providerUser->assignRole('provider');

    ProviderDetails::create([
        'user_id' => $providerUser->id,
        'npi' => '1234567899',
        'status' => 'inactive',
    ]);

    Livewire::test(PortalLoginPage::class)
        ->set('email', $providerUser->email)
        ->set('password', 'secret123')
        ->call('authenticate')
        ->assertHasErrors(['email'])
        ->assertSee('Your provider account is inactive.');

    expect(Auth::guard('web')->check())->toBeFalse();

    $activePracticeUser = User::create([
        'name' => 'Active Practice User',
        'email' => 'active-practice-throttle@example.com',
        'password' => 'secret123',
    ]);
    $activePracticeUser->assignRole('practice');

    Practice::create([
        'legal_name' => 'Active Practice LLC',
        'client_code' => 'APL',
        'email' => 'apl@example.com',
        'status' => 'active',
        'user_id' => $activePracticeUser->id,
    ]);

    RateLimiter::clear('portal-login:' . strtolower($activePracticeUser->email) . '|127.0.0.1');

    for ($i = 0; $i < 5; $i++) {
        Livewire::test(PortalLoginPage::class)
            ->set('email', $activePracticeUser->email)
            ->set('password', 'wrong-password')
            ->call('authenticate')
            ->assertHasErrors(['email']);
    }

    Livewire::test(PortalLoginPage::class)
        ->set('email', $activePracticeUser->email)
        ->set('password', 'secret123')
        ->call('authenticate')
        ->assertHasErrors(['email'])
        ->assertSee('Too many login attempts');
});

test('AdminAuthMiddleware logs out and redirects an admin whose status becomes inactive mid-session', function () {
    $admin = Admin::where('username', 'superadmin')->firstOrFail();

    $this->actingAs($admin, 'admin')
        ->get(route('admin.dashboard'))
        ->assertOk();

    $admin->update(['status' => 'inactive']);

    $this->actingAs($admin->fresh(), 'admin')
        ->get(route('admin.dashboard'))
        ->assertRedirect(route('login'));

    expect(Auth::guard('admin')->check())->toBeFalse();
});

test('PracticeAuthMiddleware and ProviderAuthMiddleware log out and redirect users whose entity status becomes inactive mid-session', function () {
    $practiceUser = User::create([
        'name' => 'Mid Session Practice User',
        'email' => 'mid-session-practice@example.com',
        'password' => 'secret123',
    ]);
    $practiceUser->assignRole('practice');

    $practice = Practice::create([
        'legal_name' => 'Mid Session Practice',
        'client_code' => 'MSP',
        'email' => 'msp@example.com',
        'status' => 'active',
        'user_id' => $practiceUser->id,
    ]);

    $practice->update(['status' => 'inactive']);

    $this->actingAs($practiceUser->fresh(), 'web')
        ->get(route('practice.dashboard'))
        ->assertRedirect(route('practice.login'));

    expect(Auth::guard('web')->check())->toBeFalse();

    $providerUser = User::create([
        'name' => 'Mid Session Provider User',
        'email' => 'mid-session-provider@example.com',
        'password' => 'secret123',
    ]);
    $providerUser->assignRole('provider');

    $provider = ProviderDetails::create([
        'user_id' => $providerUser->id,
        'npi' => '1555555555',
        'status' => 'active',
    ]);

    $provider->update(['status' => 'inactive']);

    $this->actingAs($providerUser->fresh(), 'web')
        ->get(route('provider.dashboard'))
        ->assertRedirect(route('provider.login'));

    expect(Auth::guard('web')->check())->toBeFalse();
});
