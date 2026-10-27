<?php

namespace App\Services;

use App\Models\CredentialingCase;
use App\Models\EmailMessage;
use App\Models\Payer;
use App\Models\Practice;
use App\Models\ProviderDetails;
use Illuminate\Support\Collection;

class EmailMatchingService
{
    public function match(string $subject, string $body, ?string $fromAddress = null): array
    {
        $text = trim($subject . ' ' . $body);

        $case = $this->matchByCaseNumber($text);

        if ($case) {
            $case->loadMissing(['provider.user', 'practice', 'payer']);

            return [
                'confidence' => 'high',
                'case' => $case,
                'provider' => $case->provider,
                'practice' => $case->practice,
                'payer' => $case->payer,
                'candidates' => collect([$case]),
            ];
        }

        $matchedPayer = $this->matchPayer($text, $fromAddress);

        $provider = $this->matchByNpi($text)
            ?? $this->matchByCaqhId($text)
            ?? $this->matchProviderByEmail($fromAddress);

        if ($provider) {
            $cases = $this->activeOrAllCasesForProvider($provider->id);

            if ($cases->count() > 1 && $matchedPayer) {
                $narrowed = $cases->where('payer_id', $matchedPayer->id)->values();
                if ($narrowed->isNotEmpty()) {
                    $cases = $narrowed;
                }
            }

            $matchedCase = $cases->count() === 1 ? $cases->first() : null;

            return [
                'confidence' => $cases->count() === 1 ? 'medium' : 'low',
                'case' => $matchedCase,
                'provider' => $provider,
                'practice' => $matchedCase?->practice ?? $provider->primaryPractice(),
                'payer' => $matchedCase?->payer ?? $matchedPayer,
                'candidates' => $cases,
            ];
        }

        $practice = $this->matchPracticeByTaxId($text);
        if ($practice) {
            $cases = CredentialingCase::with(['provider.user', 'practice', 'payer'])
                ->where('practice_id', $practice->id)
                ->active()
                ->get();

            if ($cases->isEmpty()) {
                $cases = CredentialingCase::with(['provider.user', 'practice', 'payer'])
                    ->where('practice_id', $practice->id)
                    ->get();
            }

            $matchedProvider = $this->matchProviderByNameInText($text);
            if ($matchedProvider) {
                $narrowedByProvider = $cases->where('provider_id', $matchedProvider->id)->values();
                if ($narrowedByProvider->isNotEmpty()) {
                    $cases = $narrowedByProvider;
                }
            }

            if ($matchedPayer) {
                $narrowedByPayer = $cases->where('payer_id', $matchedPayer->id)->values();
                if ($narrowedByPayer->isNotEmpty()) {
                    $cases = $narrowedByPayer;
                }
            }

            $matchedCase = $cases->count() === 1 ? $cases->first() : null;

            return [
                'confidence' => $matchedCase ? 'medium' : 'low',
                'case' => $matchedCase,
                'provider' => $matchedCase?->provider ?? $matchedProvider,
                'practice' => $practice,
                'payer' => $matchedCase?->payer ?? $matchedPayer,
                'candidates' => $cases,
            ];
        }

        $namedProvider = $this->matchProviderByNameInText($text);
        if ($namedProvider && $matchedPayer) {
            $cases = $this->activeOrAllCasesForProvider($namedProvider->id)
                ->where('payer_id', $matchedPayer->id)
                ->values();

            $matchedCase = $cases->count() === 1 ? $cases->first() : null;

            return [
                'confidence' => $matchedCase ? 'medium' : 'low',
                'case' => $matchedCase,
                'provider' => $namedProvider,
                'practice' => $matchedCase?->practice ?? $namedProvider->primaryPractice(),
                'payer' => $matchedPayer,
                'candidates' => $cases,
            ];
        }

        return [
            'confidence' => 'none',
            'case' => null,
            'provider' => null,
            'practice' => null,
            'payer' => $matchedPayer,
            'candidates' => collect(),
        ];
    }

    public function isBounceOrFailure(string $subject, ?string $fromAddress = null, string $body = ''): bool
    {
        $fromLower = strtolower((string) $fromAddress);
        if ($fromLower !== '' && (
            str_contains($fromLower, 'mailer-daemon')
            || str_contains($fromLower, 'postmaster@')
        )) {
            return true;
        }

        $haystack = strtolower($subject . ' ' . ($fromAddress ?? '') . ' ' . $body);
        $indicators = [
            'mailer-daemon',
            'postmaster@',
            'undeliverable',
            'delivery status notification',
            'mail delivery failed',
            'failure notice',
            'delivery failure',
            'returned mail',
        ];

        foreach ($indicators as $indicator) {
            if (str_contains($haystack, $indicator)) {
                return true;
            }
        }

        return false;
    }

    public function categorizeQueue(EmailMessage $message): string
    {
        if (
            $message->status === 'failed'
            || $message->status === 'bounced'
            || $this->isBounceOrFailure((string) $message->subject, $message->from_address, (string) $message->body)
        ) {
            return 'failed';
        }

        if ($message->direction === 'outbound') {
            return 'sent';
        }

        if ($message->is_unlinked || ! $message->credentialing_case_id) {
            return 'unlinked';
        }

        if ($message->has_pending_attachments) {
            return 'attachments_pending';
        }

        return str_contains(strtolower((string) $message->from_address), 'payer')
            ? 'payer_responses'
            : 'provider_responses';
    }

    protected function matchByCaseNumber(string $text): ?CredentialingCase
    {
        if (preg_match_all('/\b([A-Z0-9]{2,12}-\d{4}-\d{4})\b/i', $text, $matches)) {
            foreach ($matches[1] as $candidate) {
                $case = CredentialingCase::where('case_number', strtoupper($candidate))->first();
                if ($case) {
                    return $case;
                }
            }
        }

        return null;
    }

    protected function matchByNpi(string $text): ?ProviderDetails
    {
        if (preg_match_all('/\b(\d{10})\b/', $text, $matches)) {
            foreach ($matches[1] as $candidate) {
                $provider = ProviderDetails::where('npi', $candidate)->first();
                if ($provider) {
                    return $provider;
                }
            }
        }

        return null;
    }

    protected function matchByCaqhId(string $text): ?ProviderDetails
    {
        if (trim($text) === '') {
            return null;
        }

        $providers = ProviderDetails::whereNotNull('caqh_id')
            ->where('caqh_id', '!=', '')
            ->get();

        foreach ($providers as $provider) {
            $caqh = trim((string) $provider->caqh_id);
            $len = strlen($caqh);
            if ($len < 6 || $len > 12) {
                continue;
            }

            if (preg_match('/\b' . preg_quote($caqh, '/') . '\b/i', $text)) {
                return $provider;
            }
        }

        return null;
    }

    protected function matchPracticeByTaxId(string $text): ?Practice
    {
        if (trim($text) === '') {
            return null;
        }

        $practices = Practice::whereNotNull('ein_tin')
            ->where('ein_tin', '!=', '')
            ->get();

        foreach ($practices as $practice) {
            $raw = trim((string) $practice->ein_tin);
            if ($raw === '') {
                continue;
            }

            $digits = preg_replace('/\D/', '', $raw) ?? '';
            $candidates = [$raw];

            if (strlen($digits) === 9) {
                $candidates[] = $digits;
                $candidates[] = substr($digits, 0, 2) . '-' . substr($digits, 2);
            }

            foreach (array_unique($candidates) as $candidate) {
                if ($candidate !== '' && preg_match('/\b' . preg_quote($candidate, '/') . '\b/i', $text)) {
                    return $practice;
                }
            }
        }

        return null;
    }

    protected function matchProviderByNameInText(string $text): ?ProviderDetails
    {
        if (trim($text) === '') {
            return null;
        }

        $providers = ProviderDetails::with('user')->get();
        foreach ($providers as $provider) {
            $fullName = trim((string) ($provider->user->name ?? ''));
            if (strlen($fullName) < 3) {
                continue;
            }

            $stripped = trim((string) preg_replace('/^(dr\.?|doctor)\s+/i', '', $fullName));
            $namesToCheck = array_filter(array_unique([$fullName, $stripped]), fn ($n) => strlen($n) >= 3);

            foreach ($namesToCheck as $nameCandidate) {
                if (stripos($text, $nameCandidate) !== false) {
                    return $provider;
                }
            }
        }

        return null;
    }

    protected function matchPayer(string $text, ?string $fromAddress = null): ?Payer
    {
        $fromDomain = null;
        if (filled($fromAddress) && str_contains($fromAddress, '@')) {
            $fromDomain = strtolower( trim((string) substr(strrchr($fromAddress, '@'), 1)) );
        }

        $payers = Payer::all();
        foreach ($payers as $payer) {
            $name = trim((string) $payer->name);
            if (strlen($name) >= 3 && stripos($text, $name) !== false) {
                return $payer;
            }

            if ($fromDomain) {
                if (filled($payer->email) && str_contains($payer->email, '@')) {
                    $payerEmailDomain = strtolower(trim((string) substr(strrchr($payer->email, '@'), 1)));
                    if ($payerEmailDomain !== '' && $payerEmailDomain === $fromDomain) {
                        return $payer;
                    }
                }

                $normalizedName = strtolower(preg_replace('/[^a-z0-9]/i', '', $name) ?? '');
                $domainRoot = explode('.', $fromDomain)[0] ?? '';
                if (
                    strlen($normalizedName) >= 3
                    && $domainRoot !== ''
                    && (str_contains($fromDomain, $normalizedName) || str_contains($normalizedName, $domainRoot))
                ) {
                    return $payer;
                }
            }
        }

        return null;
    }

    protected function activeOrAllCasesForProvider(int $providerId): Collection
    {
        $cases = CredentialingCase::with(['provider.user', 'practice', 'payer'])
            ->where('provider_id', $providerId)
            ->active()
            ->get();

        if ($cases->isEmpty()) {
            $cases = CredentialingCase::with(['provider.user', 'practice', 'payer'])
                ->where('provider_id', $providerId)
                ->get();
        }

        return $cases;
    }

    protected function matchProviderByEmail(?string $email): ?ProviderDetails
    {
        if (! $email) {
            return null;
        }

        return ProviderDetails::whereHas('user', fn ($q) => $q->where('email', $email))->first();
    }

    public function searchCandidates(string $term): Collection
    {
        return CredentialingCase::with(['provider.user', 'payer'])
            ->where('case_number', 'like', "%{$term}%")
            ->orWhereHas('provider.user', fn ($q) => $q->where('name', 'like', "%{$term}%"))
            ->limit(10)
            ->get();
    }
}

