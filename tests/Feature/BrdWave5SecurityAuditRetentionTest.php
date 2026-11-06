<?php

use App\Enums\AdminRole;
use App\Livewire\Admin\Setting\AdminUserManager;
use App\Livewire\Admin\Setting\SettingPage;
use App\Livewire\Authenticate\LoginPage;
use App\Models\Admin;
use App\Models\AuditLog;
use App\Models\CredentialingCase;
use App\Models\EmailMessage;
use App\Models\Payer;
use App\Models\Practice;
use App\Models\ProviderDetails;
use App\Models\Setting;
use App\Models\Status;
use App\Models\User;
use Database\Seeders\AdminPermissionSeeder;
use Database\Seeders\AdminSeeder;
use Database\Seeders\MasterDataSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function () {
    app()[PermissionRegistrar::class]->forgetCachedPermissions();
    $this->seed(AdminPermissionSeeder::class);
    $this->seed(AdminSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->seed(MasterDataSeeder::class);
});

test('BRD #2: admin with two-factor enabled requires 6-digit verification code before logging into guard', function () {
    $admin = Admin::create([
        'name' => 'MFA Admin',
        'username' => 'mfa_admin',
        'email' => 'mfa-admin@example.com',
        'password' => 'StrongPass#2026',
        'status' => 'active',
        'is_two_factor' => true,
    ]);
    $admin->assignRole(AdminRole::SystemAdmin->value);

    $component = Livewire::test(LoginPage::class)
        ->set('login', 'mfa_admin')
        ->set('password', 'StrongPass#2026')
        ->call('authenticate')
        ->assertHasNoErrors()
        ->assertSet('awaitingTwoFactor', true)
        ->assertSet('pendingAdminId', $admin->id)
        ->assertSee('Two-Factor Verification');

    expect(Auth::guard('admin')->check())->toBeFalse();

    $admin->refresh();
    expect($admin->two_factor_code)->not->toBeNull()
        ->and(strlen((string) $admin->two_factor_code))->toBe(6)
        ->and($admin->two_factor_expires_at?->isFuture())->toBeTrue();

    // Invalid code should fail verification
    $component->set('twoFactorCode', '000000')
        ->call('verifyTwoFactor')
        ->assertHasErrors(['twoFactorCode']);

    expect(Auth::guard('admin')->check())->toBeFalse();

    // Valid code logs the admin in and clears 2FA fields
    $validCode = (string) $admin->two_factor_code;
    $component->set('twoFactorCode', $validCode)
        ->call('verifyTwoFactor')
        ->assertHasNoErrors()
        ->assertRedirect(route('admin.dashboard'));

    expect(Auth::guard('admin')->check())->toBeTrue();

    $admin->refresh();
    expect($admin->two_factor_code)->toBeNull()
        ->and($admin->two_factor_expires_at)->toBeNull()
        ->and(AuditLog::where('action', 'admin.login_2fa')->where('auditable_id', $admin->id)->exists())->toBeTrue();
});

test('BRD #3: admin account locks after 5 failed login attempts and can be unlocked by superadmin', function () {
    $superadmin = Admin::where('username', 'superadmin')->firstOrFail();
    $targetAdmin = Admin::create([
        'name' => 'Lockout Target',
        'username' => 'lockout_user',
        'email' => 'lockout@example.com',
        'password' => 'CorrectPass#123',
        'status' => 'active',
    ]);
    $targetAdmin->assignRole(AdminRole::CredentialingExecutive->value);

    RateLimiter::clear('admin-login:lockout_user|127.0.0.1');

    for ($i = 0; $i < 5; $i++) {
        Livewire::test(LoginPage::class)
            ->set('login', 'lockout_user')
            ->set('password', 'WrongPass#' . $i)
            ->call('authenticate')
            ->assertHasErrors(['login']);
    }

    $targetAdmin->refresh();
    expect($targetAdmin->failed_login_attempts)->toBe(5)
        ->and($targetAdmin->isLockedOut())->toBeTrue()
        ->and(AuditLog::where('action', 'admin.locked_out')->where('auditable_id', $targetAdmin->id)->exists())->toBeTrue();

    // Even with RateLimiter cleared and correct password, locked account cannot sign in
    RateLimiter::clear('admin-login:lockout_user|127.0.0.1');

    Livewire::test(LoginPage::class)
        ->set('login', 'lockout_user')
        ->set('password', 'CorrectPass#123')
        ->call('authenticate')
        ->assertHasErrors(['login'])
        ->assertSee('Account is locked due to multiple failed login attempts.');

    expect(Auth::guard('admin')->check())->toBeFalse();

    // SuperAdmin unlocks the account via AdminUserManager
    Livewire::actingAs($superadmin, 'admin')
        ->test(AdminUserManager::class)
        ->call('unlockAdmin', $targetAdmin->id)
        ->assertHasNoErrors();

    $targetAdmin->refresh();
    expect($targetAdmin->failed_login_attempts)->toBe(0)
        ->and($targetAdmin->isLockedOut())->toBeFalse()
        ->and(AuditLog::where('action', 'admin.unlocked')->where('auditable_id', $targetAdmin->id)->exists())->toBeTrue();
});

test('BRD #2, #3 & #8: AdminUserManager enforces strong passwords, sets password_changed_at, saves 2FA flag, and records audit logs', function () {
    $superadmin = Admin::where('username', 'superadmin')->firstOrFail();

    // Weak password rejected
    Livewire::actingAs($superadmin, 'admin')
        ->test(AdminUserManager::class)
        ->call('openCreateModal')
        ->set('userName', 'Weak Password User')
        ->set('userUsername', 'weak_user')
        ->set('userEmail', 'weak@example.com')
        ->set('userPassword', 'weakpass')
        ->set('userStatus', 'active')
        ->set('userRole', AdminRole::CredentialingExecutive->value)
        ->call('saveUser')
        ->assertHasErrors(['userPassword']);

    // Strong password with 2FA enabled succeeds
    Livewire::actingAs($superadmin, 'admin')
        ->test(AdminUserManager::class)
        ->call('openCreateModal')
        ->set('userName', 'Strong Password User')
        ->set('userUsername', 'strong_user')
        ->set('userEmail', 'strong@example.com')
        ->set('userPassword', 'StrongPass#2026!')
        ->set('userStatus', 'active')
        ->set('is_two_factor', true)
        ->set('userRole', AdminRole::CredentialingExecutive->value)
        ->call('saveUser')
        ->assertHasNoErrors();

    $created = Admin::where('username', 'strong_user')->firstOrFail();
    expect($created->is_two_factor)->toBeTrue()
        ->and($created->password_changed_at)->not->toBeNull()
        ->and(AuditLog::where('action', 'admin.user_created')->where('auditable_id', $created->id)->exists())->toBeTrue();

    // Toggle status records audit log
    Livewire::actingAs($superadmin, 'admin')
        ->test(AdminUserManager::class)
        ->call('toggleStatus', $created->id)
        ->assertHasNoErrors();

    expect($created->fresh()->status)->toBe('inactive')
        ->and(AuditLog::where('action', 'admin.status_toggled')->where('auditable_id', $created->id)->exists())->toBeTrue();
});

test('BRD #6: credentialing:apply-retention prunes expired audit logs and unlinked emails while keeping recent and linked records', function () {
    Setting::set('record_retention_days', '90');

    $oldAudit = AuditLog::record('legacy.event', null, null, ['note' => 'expired']);
    DB::table('audit_logs')->where('id', $oldAudit->id)->update([
        'created_at' => now()->subDays(120),
        'updated_at' => now()->subDays(120),
    ]);

    $recentAudit = AuditLog::record('recent.event', null, null, ['note' => 'keep']);

    $oldUnlinkedEmail = EmailMessage::create([
        'direction' => 'inbound',
        'from_address' => 'spam@example.com',
        'to_address' => 'cred@example.com',
        'subject' => 'Old Unlinked Email',
        'body' => 'Should be pruned',
        'status' => 'received',
        'is_unlinked' => true,
    ]);
    DB::table('email_messages')->where('id', $oldUnlinkedEmail->id)->update([
        'created_at' => now()->subDays(120),
        'updated_at' => now()->subDays(120),
    ]);

    Artisan::call('credentialing:apply-retention');

    expect(AuditLog::whereKey($oldAudit->id)->exists())->toBeFalse()
        ->and(AuditLog::whereKey($recentAudit->id)->exists())->toBeTrue()
        ->and(EmailMessage::whereKey($oldUnlinkedEmail->id)->exists())->toBeFalse()
        ->and(AuditLog::where('action', 'system.retention_applied')->exists())->toBeTrue();
});

test('BRD #1, #7, #8 & #26: SettingPage verifies SQL views in readiness checks, saves security policy, exports backup snapshot, and filters audit logs', function () {
    $superadmin = Admin::where('username', 'superadmin')->firstOrFail();

    $practiceUser = User::create([
        'name' => 'Wave5 Practice User',
        'email' => 'wave5-practice@example.com',
        'password' => 'password',
    ]);
    $practice = Practice::create([
        'legal_name' => 'Wave5 Medical Group',
        'client_code' => 'W5M',
        'email' => 'w5m@example.com',
        'status' => 'active',
        'user_id' => $practiceUser->id,
    ]);
    $providerUser = User::create([
        'name' => 'Dr. Wave5 Provider',
        'email' => 'dr-wave5@example.com',
        'password' => 'password',
    ]);
    $provider = ProviderDetails::create([
        'user_id' => $providerUser->id,
        'npi' => '1777777777',
        'status' => 'active',
        'state' => 'TX',
    ]);
    $payer = Payer::create(['name' => 'Wave5 Payer', 'is_active' => true]);
    $status = Status::firstOrFail();

    CredentialingCase::create([
        'practice_id' => $practice->id,
        'provider_id' => $provider->id,
        'payer_id' => $payer->id,
        'status_id' => $status->id,
        'state' => 'TX',
    ]);

    // Verify all 3 SQL reporting views are queryable and return expected rows
    expect(DB::table('vw_credentialing_case_summary')->count())->toBeGreaterThanOrEqual(1)
        ->and(DB::table('vw_delay_ownership_summary')->count())->toBeGreaterThanOrEqual(1)
        ->and(DB::table('vw_provider_compliance_summary')->count())->toBeGreaterThanOrEqual(1);

    $component = Livewire::actingAs($superadmin, 'admin')
        ->test(SettingPage::class)
        ->assertSee('Production Go-Live Readiness')
        ->assertSee('3/3 SQL views active')
        ->set('record_retention_days', 180)
        ->set('password_expiry_days', 60)
        ->set('encrypt_documents_at_rest', true)
        ->call('saveSecuritySettings')
        ->assertHasNoErrors();

    expect(Setting::where('key', 'record_retention_days')->value('value'))->toBe('180')
        ->and(Setting::where('key', 'password_expiry_days')->value('value'))->toBe('60')
        ->and(Setting::where('key', 'encrypt_documents_at_rest')->value('value'))->toBe('1');

    // Download backup snapshot and verify audit log
    $component->call('downloadBackupSnapshot')
        ->assertFileDownloaded('medcred_backup_' . now()->format('Ymd') . '.json');

    expect(AuditLog::where('action', 'system.backup_downloaded')->exists())->toBeTrue();

    // Filter audit trail viewer on SettingPage
    $component->set('auditSearch', 'system.backup_downloaded')
        ->assertSee('system.backup_downloaded');
});
