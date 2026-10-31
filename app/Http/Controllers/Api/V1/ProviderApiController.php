<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\CredentialingCase;
use App\Models\Document;
use App\Models\ProviderDetails;
use App\Models\User;
use App\Services\AdminScopeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;

class ProviderApiController extends Controller
{
    public function index(Request $request, AdminScopeService $scope): JsonResponse
    {
        $user = $request->user();

        if ($user instanceof Admin) {
            if (! $user->can('admin.providers.view')) {
                return response()->json(['status' => 'error', 'message' => 'Forbidden.'], 403);
            }

            $query = ProviderDetails::query()->with(['user:id,name,email', 'specialty:id,name', 'practices:id,legal_name']);
            $scope->scopeProviders($query, $user);

            return response()->json(['status' => 'ok', 'data' => $query->latest()->get()]);
        }

        if ($user instanceof User) {
            $practiceId = $user->practice_id ?? $user->practice?->id;

            $providers = ProviderDetails::query()
                ->with(['user:id,name,email', 'specialty:id,name', 'practices:id,legal_name'])
                ->where(function ($q) use ($user, $practiceId) {
                    $q->where('user_id', $user->id);
                    if ($practiceId) {
                        $q->orWhereHas('practices', fn ($pq) => $pq->where('practices.id', $practiceId));
                    }
                })
                ->latest()
                ->get();

            return response()->json(['status' => 'ok', 'data' => $providers]);
        }

        return response()->json(['status' => 'error', 'message' => 'Forbidden.'], 403);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $provider = ProviderDetails::with(['user:id,name,email', 'specialty:id,name', 'practices:id,legal_name'])->find($id);

        if (! $provider) {
            return response()->json(['status' => 'error', 'message' => 'Provider not found.'], 404);
        }

        if (! $this->canAccessProvider($request->user(), $provider)) {
            return response()->json(['status' => 'error', 'message' => 'Forbidden.'], 403);
        }

        return response()->json(['status' => 'ok', 'data' => $provider]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! ($user instanceof Admin) || ! $user->can('admin.providers.create')) {
            return response()->json(['status' => 'error', 'message' => 'Forbidden.'], 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email',
            'npi' => 'required|digits:10|unique:provider_details,npi',
            'specialty_id' => 'nullable|exists:specialties,id',
            'practice_id' => 'nullable|exists:practices,id',
        ]);

        if (! empty($validated['practice_id']) && ! app(AdminScopeService::class)->canAccessPractice($user, (int) $validated['practice_id'])) {
            return response()->json(['status' => 'error', 'message' => 'Forbidden.'], 403);
        }

        $portalUser = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Str::random(24),
            'status' => 1,
        ]);

        try {
            $portalUser->assignRole('provider');
        } catch (\Throwable) {
        }

        $provider = ProviderDetails::create([
            'user_id' => $portalUser->id,
            'npi' => $validated['npi'],
            'specialty_id' => $validated['specialty_id'] ?? null,
            'status' => 'active',
        ]);

        if (! empty($validated['practice_id'])) {
            $provider->practices()->syncWithoutDetaching([(int) $validated['practice_id'] => ['primary_flag' => true]]);
        }

        return response()->json(['status' => 'ok', 'data' => $provider->load(['user:id,name,email', 'practices:id,legal_name'])], 201);
    }

    public function cases(Request $request, ?int $id = null): JsonResponse
    {
        $provider = $this->resolveProvider($request, $id);

        if (! $provider) {
            return response()->json(['status' => 'error', 'message' => 'Provider not found.'], 404);
        }

        $user = $request->user();

        if (! $this->canAccessProvider($user, $provider)) {
            return response()->json(['status' => 'error', 'message' => 'Forbidden.'], 403);
        }

        $query = CredentialingCase::where('provider_id', $provider->id)
            ->with(['payer:id,name', 'status:id,name,dashboard_category']);

        if ($user instanceof Admin) {
            app(AdminScopeService::class)->scopeCredentialingCases($query, $user);
        } elseif ($user instanceof User && (int) $provider->user_id !== (int) $user->id) {
            $practiceId = (int) ($user->practice_id ?? $user->practice?->id ?? 0);
            $query->where('practice_id', $practiceId);
        }

        $cases = $query
            ->latest()
            ->get()
            ->map(fn ($case) => [
                'case_number' => $case->case_number,
                'payer' => $case->payer->name ?? null,
                'status' => $case->status->name ?? null,
                'status_category' => $case->status->dashboard_category ?? null,
                'checklist' => $case->checklist_completion,
                'intake_date' => $case->intake_date?->toDateString(),
                'effective_date' => $case->effective_date?->toDateString(),
            ]);

        return response()->json(['status' => 'ok', 'data' => $cases]);
    }

    public function documents(Request $request, ?int $id = null): JsonResponse
    {
        $provider = $this->resolveProvider($request, $id);

        if (! $provider) {
            return response()->json(['status' => 'error', 'message' => 'Provider not found.'], 404);
        }

        $user = $request->user();

        if (! $this->canAccessProvider($user, $provider)) {
            return response()->json(['status' => 'error', 'message' => 'Forbidden.'], 403);
        }

        $query = Document::where('provider_id', $provider->id)
            ->with('documentType:id,name');

        if ($user instanceof Admin) {
            app(AdminScopeService::class)->scopeDocuments($query, $user);
        } elseif ($user instanceof User) {
            $query->where(function ($q) {
                $q->whereNull('visibility')
                    ->orWhereNotIn('visibility', ['restricted', 'internal']);
            });

            if ((int) $provider->user_id !== (int) $user->id) {
                $practiceId = (int) ($user->practice_id ?? $user->practice?->id ?? 0);
                $query->where(function ($q) use ($practiceId) {
                    $q->where('practice_id', $practiceId)
                        ->orWhere(function ($sq) use ($practiceId) {
                            $sq->whereNull('practice_id')
                                ->whereHas('credentialingCase', fn ($cq) => $cq->where('practice_id', $practiceId));
                        })
                        ->orWhere(function ($sq) {
                            $sq->whereNull('practice_id')
                                ->whereNull('credentialing_case_id');
                        });
                });
            }
        }

        $documents = $query
            ->latest()
            ->get()
            ->map(fn ($doc) => [
                'title' => $doc->title,
                'type' => $doc->documentType->name ?? null,
                'expiry_date' => $doc->expiry_date?->toDateString(),
                'status' => $doc->status,
            ]);

        return response()->json(['status' => 'ok', 'data' => $documents]);
    }

    public function issueToken(Request $request): JsonResponse
    {
        if ($request->user() && $request->missing(['email', 'password'])) {
            return $this->createToken($request);
        }

        $validated = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
            'guard' => 'nullable|string|in:admin,web,portal',
            'token_name' => 'nullable|string|max:255',
            'device_name' => 'nullable|string|max:255',
        ]);

        $requestedGuard = $validated['guard'] ?? null;
        if ($requestedGuard === 'admin') {
            $account = Admin::where('email', $validated['email'])->first();
        } elseif (in_array($requestedGuard, ['web', 'portal'], true)) {
            $account = User::where('email', $validated['email'])->first();
        } else {
            $account = User::where('email', $validated['email'])->first()
                ?? Admin::where('email', $validated['email'])->first();
        }

        if ($account instanceof Admin && $account->isLockedOut()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Account is locked due to multiple failed login attempts.',
            ], 403);
        }

        if (! $account || ! Hash::check($validated['password'], $account->password)) {
            if ($account instanceof Admin) {
                $attempts = ((int) $account->failed_login_attempts) + 1;
                $updates = ['failed_login_attempts' => $attempts];
                if ($attempts >= 5) {
                    $updates['locked_until'] = now()->addMinutes(15);
                }
                $account->update($updates);
            }

            return response()->json([
                'status' => 'error',
                'message' => 'The provided credentials are incorrect or the account is inactive.',
            ], 401);
        }

        if (! $this->isActiveAccount($account)) {
            return response()->json([
                'status' => 'error',
                'message' => 'The provided credentials are incorrect or the account is inactive.',
            ], 401);
        }

        if ($account instanceof Admin) {
            if ($account->is_two_factor) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Two-factor authentication is required for this admin account.',
                ], 403);
            }

            $account->update([
                'failed_login_attempts' => 0,
                'locked_until' => null,
            ]);
        }

        $tokenName = $validated['token_name'] ?? $validated['device_name'] ?? 'api-token';
        $plainTextToken = $this->generateSanctumToken($account, $tokenName);

        return response()->json([
            'status' => 'ok',
            'token' => $plainTextToken,
        ]);
    }

    public function createToken(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'device_name' => 'nullable|string|max:255',
            'token_name' => 'nullable|string|max:255',
        ]);

        $user = $request->user();
        if (! $user) {
            return response()->json(['status' => 'error', 'message' => 'Unauthenticated.'], 401);
        }

        $tokenName = $validated['token_name'] ?? $validated['device_name'] ?? 'api-token';
        $plainTextToken = $this->generateSanctumToken($user, $tokenName);

        return response()->json([
            'status' => 'ok',
            'token' => $plainTextToken,
        ]);
    }

    protected function resolveProvider(Request $request, ?int $id = null): ?ProviderDetails
    {
        if ($id !== null) {
            return ProviderDetails::find($id);
        }

        $user = $request->user();

        return $user instanceof User ? $user->providerDetails : null;
    }

    protected function canAccessProvider(mixed $user, ProviderDetails $provider): bool
    {
        if ($user instanceof Admin) {
            return $user->can('admin.providers.view')
                && app(AdminScopeService::class)->canAccessProvider($user, $provider);
        }

        if ($user instanceof User) {
            if ((int) $provider->user_id === (int) $user->id) {
                return true;
            }

            $practiceId = $user->practice_id ?? $user->practice?->id;
            if ($practiceId && $provider->practices()->where('practices.id', (int) $practiceId)->exists()) {
                return true;
            }

            return false;
        }

        return false;
    }

    protected function isActiveAccount(User|Admin $account): bool
    {
        if ($account instanceof Admin) {
            if (method_exists($account, 'trashed') && $account->trashed()) {
                return false;
            }

            return in_array($account->status, ['active', 1, '1', true], true);
        }

        if ((int) ($account->status ?? 1) === 0 || $account->status === 'inactive') {
            return false;
        }

        if ($account->providerDetails) {
            $providerStatus = $account->providerDetails->status?->value ?? $account->providerDetails->status;
            if ($providerStatus === 'inactive' || ($account->providerDetails->is_active ?? true) === false) {
                return false;
            }
        }

        if ($account->practice) {
            $practiceStatus = $account->practice->status?->value ?? $account->practice->status;
            if ($practiceStatus === 'inactive') {
                return false;
            }
        }

        return true;
    }

    protected function generateSanctumToken(mixed $account, string $tokenName): string
    {
        if (method_exists($account, 'createToken')) {
            return $account->createToken($tokenName)->plainTextToken;
        }

        $plainTextToken = sprintf(
            '%s%s%s',
            config('sanctum.token_prefix', ''),
            $tokenEntropy = Str::random(40),
            hash('crc32b', $tokenEntropy)
        );

        $token = PersonalAccessToken::forceCreate([
            'tokenable_type' => $account->getMorphClass(),
            'tokenable_id' => $account->getKey(),
            'name' => $tokenName,
            'token' => hash('sha256', $plainTextToken),
            'abilities' => ['*'],
        ]);

        return $token->getKey().'|'.$plainTextToken;
    }
}
