<?php

namespace App\Services;

use App\Models\CredentialingCase;
use App\Models\EmailCaseLink;
use Illuminate\Support\Collection;

class EmailCaseLinkService
{
    public function link(string $messageId, int $caseId): EmailCaseLink
    {
        $normalized = EmailCaseLink::normalizeMessageId($messageId);

        if (blank($normalized)) {
            throw new \InvalidArgumentException('A Message-ID is required to link an email to a case.');
        }

        CredentialingCase::query()->findOrFail($caseId);

        return EmailCaseLink::query()->updateOrCreate(
            ['message_id' => $normalized],
            ['credentialing_case_id' => $caseId],
        );
    }

    public function unlink(string $messageId): void
    {
        $normalized = EmailCaseLink::normalizeMessageId($messageId);

        if (blank($normalized)) {
            return;
        }

        EmailCaseLink::query()->where('message_id', $normalized)->delete();
    }

    public function findCaseId(?string $messageId): ?int
    {
        $normalized = EmailCaseLink::normalizeMessageId($messageId);

        if (blank($normalized)) {
            return null;
        }

        return EmailCaseLink::query()
            ->where('message_id', $normalized)
            ->value('credentialing_case_id');
    }

    public function findCase(?string $messageId): ?CredentialingCase
    {
        $caseId = $this->findCaseId($messageId);

        return $caseId ? CredentialingCase::with('provider.user')->find($caseId) : null;
    }

    /**
     * @param  list<string|null>  $messageIds
     * @return array<string, int> normalized message_id => case_id
     */
    public function caseIdsForMessageIds(array $messageIds): array
    {
        $normalized = collect($messageIds)
            ->map(fn ($id) => EmailCaseLink::normalizeMessageId($id))
            ->filter()
            ->unique()
            ->values();

        if ($normalized->isEmpty()) {
            return [];
        }

        return EmailCaseLink::query()
            ->whereIn('message_id', $normalized->all())
            ->pluck('credentialing_case_id', 'message_id')
            ->all();
    }

    /**
     * @return Collection<int, EmailCaseLink>
     */
    public function linksForCase(int $caseId): Collection
    {
        return EmailCaseLink::query()
            ->where('credentialing_case_id', $caseId)
            ->latest()
            ->get();
    }

    /**
     * @param  list<int>  $caseIds
     * @return Collection<int, EmailCaseLink>
     */
    public function linksForCases(array $caseIds): Collection
    {
        if ($caseIds === []) {
            return collect();
        }

        return EmailCaseLink::query()
            ->with('credentialingCase')
            ->whereIn('credentialing_case_id', $caseIds)
            ->latest()
            ->get();
    }
}
