<?php

namespace App\Services;

use App\Models\CredentialingCase;
use App\Models\EmailMessage;
use App\Models\ProviderDetails;
use Illuminate\Support\Collection;

class EmailMatchingService
{
    public function match(string $subject, string $body, ?string $fromAddress = null): array
    {
        $case = $this->matchByCaseNumber($subject . ' ' . $body);

        if ($case) {
            return ['confidence' => 'high', 'case' => $case, 'provider' => $case->provider];
        }

        $provider = $this->matchByNpi($subject . ' ' . $body)
            ?? $this->matchProviderByEmail($fromAddress);

        if ($provider) {
            $cases = CredentialingCase::where('provider_id', $provider->id)->active()->get();

            return [
                'confidence' => $cases->count() === 1 ? 'medium' : 'low',
                'case' => $cases->count() === 1 ? $cases->first() : null,
                'provider' => $provider,
                'candidates' => $cases,
            ];
        }

        return ['confidence' => 'none', 'case' => null, 'provider' => null];
    }

    public function categorizeQueue(EmailMessage $message): string
    {
        if ($message->status === 'failed' || $message->status === 'bounced') {
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
        if (preg_match('/\b([A-Z]{3}-\d{4}-\d{4})\b/', strtoupper($text), $matches)) {
            return CredentialingCase::where('case_number', $matches[1])->first();
        }

        // Legacy APP-YYYY-NNNN numbers
        if (preg_match('/\b(APP-\d{4}-\d{4})\b/', strtoupper($text), $matches)) {
            return CredentialingCase::where('case_number', $matches[1])->first();
        }

        return null;
    }

    protected function matchByNpi(string $text): ?ProviderDetails
    {
        if (preg_match('/\b\d{10}\b/', $text, $matches)) {
            return ProviderDetails::where('npi', $matches[0])->first();
        }

        return null;
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
