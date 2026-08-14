<?php

namespace App\Listeners;

use App\Events\CaseStatusChanged;
use App\Events\DocumentRequestSent;
use App\Events\DocumentVerified;
use App\Events\ProviderResponseReceived;
use App\Services\FollowUpEngine;
use App\Services\TaskSyncService;

class FollowUpEngineListener
{
    public function __construct(
        protected FollowUpEngine $engine,
        protected TaskSyncService $taskSync,
    ) {
    }

    public function handleDocumentRequestSent(DocumentRequestSent $event): void
    {
        $this->engine->onDocumentRequestSent($event);
    }

    public function handleCaseStatusChanged(CaseStatusChanged $event): void
    {
        if ($event->case->do_not_automate) {
            return;
        }

        $case = $event->case->fresh(['status', 'documentItems']);

        foreach ($case->documentItems->where('is_required', true)->where('is_received', false) as $item) {
            $this->taskSync->ensureDocumentTask($case, $item, $event->adminId);
        }

        $this->engine->onCaseStatusChanged($case, $event->oldStatusId, $event->newStatusId, $event->adminId);
    }

    public function handleProviderResponseReceived(ProviderResponseReceived $event): void
    {
        $this->engine->onProviderResponseReceived($event);
    }

    public function handleDocumentVerified(DocumentVerified $event): void
    {
        $document = $event->document;

        if ($document->credentialing_case_id) {
            $document->credentialingCase?->syncChecklistFromDocument($document);
        }
    }

    public function subscribe($events): array
    {
        return [
            DocumentRequestSent::class => 'handleDocumentRequestSent',
            CaseStatusChanged::class => 'handleCaseStatusChanged',
            ProviderResponseReceived::class => 'handleProviderResponseReceived',
            DocumentVerified::class => 'handleDocumentVerified',
        ];
    }
}
