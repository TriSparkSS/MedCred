<?php

namespace App\Livewire\Admin\Email;

use App\Data\MailMessageDto;
use App\Models\Admin;
use App\Models\AuditLog;
use App\Models\CredentialingCase;
use App\Models\DocumentType;
use App\Models\EmailAttachment;
use App\Models\EmailMessage;
use App\Models\NotificationTemplate;
use App\Models\ProviderDetails;
use App\Services\AdminScopeService;
use App\Services\CredentialingEmailService;
use App\Services\DocumentService;
use App\Services\EmailCaseLinkService;
use App\Services\EmailMatchingService;
use App\Services\GraphMailboxService;
use App\Services\MailSettingsService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Symfony\Component\HttpKernel\Exception\HttpException;

#[Layout('layouts::admin', ['title' => 'Email Conversation'])]
class EmailViewPage extends Component
{
    #[Locked]
    public string $folder = 'inbox';

    #[Locked]
    public string $messageId = '';

    public bool $showLinkModal = false;

    public string $linkCaseId = '';

    public string $linkMessageId = '';

    public bool $showComposeModal = false;

    public string $composeCaseId = '';

    public string $composeTemplateId = '';

    public string $composeTo = '';

    public string $composeSubject = '';

    public string $composeBody = '';

    public string $composeInReplyTo = '';

    public ?string $loadError = null;

    public bool $showSaveAttachmentModal = false;

    public string $ingestMessageId = '';

    public string $ingestAttachmentId = '';

    public string $ingestAttachmentName = '';

    public string $ingestDocumentTypeId = '';

    public string $ingestCaseId = '';

    public string $ingestProviderId = '';

    public string $ingestTitle = '';

    public string $ingestExpiryDate = '';

    public string $ingestNotes = '';

    protected function canSendEmail(): bool
    {
        $admin = Auth::guard('admin')->user();

        return (bool) ($admin && ($admin->can('admin.emails.send') || $admin->can('admin.email.send')));
    }

    protected function canLinkEmail(): bool
    {
        $admin = Auth::guard('admin')->user();

        return (bool) ($admin && ($admin->can('admin.emails.link') || $admin->can('admin.email.link')));
    }

    protected function canIngestAttachment(): bool
    {
        $admin = Auth::guard('admin')->user();

        return (bool) ($admin && (
            $admin->can('admin.documents.upload')
            || $admin->can('admin.emails.link')
            || $admin->can('admin.email.link')
        ));
    }

    public function mount(string $folder = 'inbox', string $messageId = ''): void
    {
        $this->folder = app(GraphMailboxService::class)->normalizeFolder($folder);

        if ($messageId !== '' && ! self::isValidEncodedId($messageId)) {
            abort(404);
        }

        // Keep URL-safe encoding in Livewire state — decoded Graph ids may contain non-UTF-8 bytes.
        $this->messageId = $messageId;
    }

    public function openLinkModal(?string $internetMessageId = null): void
    {
        abort_unless($this->canLinkEmail(), 403);

        $this->linkMessageId = $internetMessageId ?: $this->linkMessageId;
        $this->showLinkModal = true;
    }

    public function closeLinkModal(): void
    {
        $this->showLinkModal = false;
        $this->linkCaseId = '';
    }

    public function linkToCase(CredentialingEmailService $emailService): void
    {
        $admin = Auth::guard('admin')->user();
        abort_unless($this->canLinkEmail(), 403);

        $this->validate([
            'linkCaseId' => 'required|exists:credentialing_cases,id',
            'linkMessageId' => 'required|string',
        ]);

        $case = CredentialingCase::findOrFail((int) $this->linkCaseId);
        abort_unless($admin && app(AdminScopeService::class)->canAccessCase($admin, $case), 403);

        $emailService->linkMessageToCase($this->linkMessageId, $case, $admin->id);
        $this->closeLinkModal();
        flash()->success('Email linked to case '.$case->case_number);
    }

    public function linkEmail(CredentialingEmailService $emailService): void
    {
        $this->linkToCase($emailService);
    }

    public function unlinkCase(?string $messageId = null): void
    {
        $admin = Auth::guard('admin')->user();
        abort_unless($this->canLinkEmail(), 403);

        $targetMessageId = $messageId ?: $this->linkMessageId;
        if (blank($targetMessageId)) {
            return;
        }

        $caseLinks = app(EmailCaseLinkService::class);
        $existingCase = $caseLinks->findCase($targetMessageId);
        if ($existingCase) {
            abort_unless($admin && app(AdminScopeService::class)->canAccessCase($admin, $existingCase), 403);
        }

        $caseLinks->unlink($targetMessageId);
        $this->linkCaseId = '';
        flash()->info('Email unlinked from case.');
    }

    public function openReplyModal(string $to, string $subject, string $inReplyTo = '', string $caseId = ''): void
    {
        abort_unless($this->canSendEmail(), 403);

        $this->composeTo = $to;
        $this->composeSubject = str_starts_with(strtolower($subject), 're:') ? $subject : 'Re: '.$subject;
        $this->composeBody = '';
        $this->composeInReplyTo = $inReplyTo;
        $this->composeCaseId = $caseId;
        $this->composeTemplateId = '';
        $this->showComposeModal = true;
    }

    public function closeComposeModal(): void
    {
        $this->showComposeModal = false;
    }

    public function sendEmail(CredentialingEmailService $emailService, MailSettingsService $mailSettings, GraphMailboxService $graph): void
    {
        $admin = Auth::guard('admin')->user();
        abort_unless($this->canSendEmail(), 403);

        $this->validate([
            'composeTo' => 'required|email',
            'composeSubject' => 'required|string|max:500',
            'composeBody' => 'required|string',
            'composeCaseId' => 'nullable|exists:credentialing_cases,id',
        ]);

        $case = $this->composeCaseId ? CredentialingCase::findOrFail((int) $this->composeCaseId) : null;
        if ($case) {
            abort_unless($admin && app(AdminScopeService::class)->canAccessCase($admin, $case), 403);
        }

        $mailSettings->assertConfigured();

        $template = $this->composeTemplateId ? NotificationTemplate::find((int) $this->composeTemplateId) : null;

        if ($template && $case) {
            $result = $emailService->sendFromTemplate(
                $case,
                $template,
                $this->composeTo,
                $admin->id,
                null,
                [],
                $this->composeInReplyTo ?: null,
            );
        } else {
            $result = $emailService->send(
                $case,
                $this->composeTo,
                $this->composeSubject,
                $this->composeBody,
                $admin->id,
                $template,
                null,
                $this->composeInReplyTo ?: null,
            );
        }

        if ($result->failed()) {
            flash()->error('Email failed: '.($result->errorMessage ?? 'Unknown error'));

            return;
        }

        $graph->clearCache();
        flash()->success('Reply sent.');
        $this->closeComposeModal();
    }

    public function sendReply(CredentialingEmailService $emailService, MailSettingsService $mailSettings, GraphMailboxService $graph): void
    {
        $this->sendEmail($emailService, $mailSettings, $graph);
    }

    public function sendForward(CredentialingEmailService $emailService, MailSettingsService $mailSettings, GraphMailboxService $graph): void
    {
        $this->sendEmail($emailService, $mailSettings, $graph);
    }

    public function openSaveAttachmentModal(
        string $encodedMessageId,
        string $encodedAttachmentId,
        string $attachmentName = '',
        string $caseId = '',
    ): void {
        abort_unless($this->canIngestAttachment(), 403);

        if (! self::isValidEncodedId($encodedMessageId) || ! self::isValidEncodedId($encodedAttachmentId)) {
            abort(404);
        }

        $this->resetErrorBag();
        $this->ingestMessageId = $encodedMessageId;
        $this->ingestAttachmentId = $encodedAttachmentId;
        $this->ingestAttachmentName = $attachmentName;
        $this->ingestTitle = pathinfo($attachmentName, PATHINFO_FILENAME) ?: ($attachmentName ?: 'Email Attachment');
        $this->ingestCaseId = $caseId !== '' ? $caseId : $this->linkCaseId;
        $this->ingestProviderId = '';

        if ($this->ingestCaseId !== '') {
            $case = CredentialingCase::find((int) $this->ingestCaseId);
            if ($case && $case->provider_id) {
                $this->ingestProviderId = (string) $case->provider_id;
            }
        }

        $this->ingestDocumentTypeId = '';
        $this->ingestExpiryDate = '';
        $this->ingestNotes = '';
        $this->showSaveAttachmentModal = true;
    }

    public function closeSaveAttachmentModal(): void
    {
        $this->showSaveAttachmentModal = false;
        $this->resetErrorBag();
    }

    public function updatedIngestCaseId($value): void
    {
        if (! $value) {
            return;
        }

        $case = CredentialingCase::find((int) $value);
        if ($case && $case->provider_id) {
            $this->ingestProviderId = (string) $case->provider_id;
        }
    }

    public function saveAttachmentToDocuments(GraphMailboxService $graph, DocumentService $documentService): void
    {
        $admin = Auth::guard('admin')->user();
        abort_unless($this->canIngestAttachment(), 403);

        $this->validate([
            'ingestMessageId' => 'required|string',
            'ingestAttachmentId' => 'required|string',
            'ingestDocumentTypeId' => 'required|exists:document_types,id',
            'ingestTitle' => 'required|string|max:255',
            'ingestCaseId' => 'nullable|exists:credentialing_cases,id',
            'ingestProviderId' => 'nullable|exists:provider_details,id',
            'ingestExpiryDate' => 'nullable|date',
            'ingestNotes' => 'nullable|string|max:1000',
        ]);

        if ($this->ingestCaseId === '' && $this->ingestProviderId === '') {
            $this->addError('ingestCaseId', 'Select a credentialing case or provider for this document.');

            return;
        }

        $scope = app(AdminScopeService::class);
        $case = $this->ingestCaseId !== '' ? CredentialingCase::findOrFail((int) $this->ingestCaseId) : null;
        if ($case && $admin) {
            abort_unless($scope->canAccessCase($admin, $case), 403);
        }

        $providerId = $this->ingestProviderId !== '' ? (int) $this->ingestProviderId : $case?->provider_id;
        if ($providerId && $admin) {
            $provider = ProviderDetails::with('practices')->find($providerId);
            if ($provider) {
                abort_unless($scope->canAccessProvider($admin, $provider), 403);
            }
        }

        $decodedMessageId = self::decodeId($this->ingestMessageId);
        $decodedAttachmentId = self::decodeId($this->ingestAttachmentId);

        if ($admin) {
            $this->assertAdminCanAccessMessage($admin, $scope, app(EmailCaseLinkService::class), $decodedMessageId);
        }

        $downloaded = $graph->downloadAttachment($decodedMessageId, $decodedAttachmentId);
        $filename = (string) ($downloaded['name'] ?? ($this->ingestAttachmentName ?: 'attachment.pdf'));
        $mimeType = (string) ($downloaded['contentType'] ?? 'application/octet-stream');
        $content = (string) ($downloaded['content'] ?? '');

        $tmpPath = tempnam(sys_get_temp_dir(), 'email_att_');
        file_put_contents($tmpPath, $content);

        try {
            $uploadedFile = new UploadedFile($tmpPath, $filename, $mimeType, null, true);
            $document = $documentService->upload([
                'document_type_id' => (int) $this->ingestDocumentTypeId,
                'credentialing_case_id' => $case?->id,
                'provider_id' => $providerId,
                'practice_id' => $case?->practice_id,
                'payer_id' => $case?->payer_id,
                'state' => $case?->state,
                'title' => $this->ingestTitle,
                'expiry_date' => $this->ingestExpiryDate ?: null,
            ], $uploadedFile, $admin?->id);
        } finally {
            @unlink($tmpPath);
        }

        if ($this->ingestNotes !== '') {
            $document->versions()->where('is_current', true)->update(['notes' => $this->ingestNotes]);
        }

        $emailMessage = EmailMessage::where('external_message_id', $decodedMessageId)
            ->orWhere('message_id', $decodedMessageId)
            ->first();

        if ($emailMessage) {
            $emailAttachment = EmailAttachment::where('email_message_id', $emailMessage->id)
                ->where('original_name', $filename)
                ->first();

            if ($emailAttachment) {
                $emailAttachment->update(['document_id' => $document->id]);
            } else {
                $currentVersion = $document->versions()->where('is_current', true)->first();
                EmailAttachment::create([
                    'email_message_id' => $emailMessage->id,
                    'document_id' => $document->id,
                    'file_path' => $currentVersion?->file_path ?? '',
                    'original_name' => $filename,
                    'file_size' => strlen($content),
                    'mime_type' => $mimeType,
                ]);
            }

            $hasRemainingPending = $emailMessage->attachments()->whereNull('document_id')->exists();
            if (! $hasRemainingPending && $emailMessage->has_pending_attachments) {
                $emailMessage->update(['has_pending_attachments' => false]);
            }
        } elseif (ctype_digit($decodedAttachmentId)) {
            EmailAttachment::whereKey((int) $decodedAttachmentId)->update(['document_id' => $document->id]);
        }

        if ($case) {
            $case->addActivity(
                'document',
                "Email attachment saved to documents: {$document->title} ({$filename})",
                $admin?->id
            );
        }

        AuditLog::record('email.attachment_ingested', $document, $admin?->id, [
            'message_id' => $decodedMessageId,
            'attachment_id' => $decodedAttachmentId,
            'filename' => $filename,
            'credentialing_case_id' => $case?->id,
            'provider_id' => $providerId,
        ]);

        $this->closeSaveAttachmentModal();
        flash()->success("Attachment saved to documents as \"{$document->title}\".");
    }

    public function render(
        GraphMailboxService $graph,
        EmailCaseLinkService $caseLinks,
        EmailMatchingService $matching,
        MailSettingsService $mailSettings,
    ) {
        $admin = Auth::guard('admin')->user();
        $scope = app(AdminScopeService::class);

        /** @var Collection<int, MailMessageDto> $threadMessages */
        $threadMessages = collect();
        $this->loadError = null;
        $anchor = null;
        $caseMap = [];

        if (! $graph->isConfigured()) {
            $this->loadError = 'Microsoft Graph is not configured.';
        } elseif ($this->messageId !== '') {
            try {
                $decodedMessageId = self::decodeId($this->messageId);

                if ($admin) {
                    $this->assertAdminCanAccessMessage($admin, $scope, $caseLinks, $decodedMessageId);
                }

                $anchor = $graph->getMessage($decodedMessageId, $this->folder, false);

                if ($admin && filled($anchor->internetMessageId)) {
                    $this->assertAdminCanAccessMessage($admin, $scope, $caseLinks, (string) $anchor->internetMessageId);
                }

                $threadMessages = $graph->getConversation($anchor->conversationId, $decodedMessageId, $this->folder);
                $this->linkMessageId = $anchor->internetMessageId ?? '';

                if ($admin) {
                    foreach ($threadMessages as $threadMsg) {
                        if (filled($threadMsg->id)) {
                            $this->assertAdminCanAccessMessage($admin, $scope, $caseLinks, (string) $threadMsg->id);
                        }
                        if (filled($threadMsg->internetMessageId)) {
                            $this->assertAdminCanAccessMessage($admin, $scope, $caseLinks, (string) $threadMsg->internetMessageId);
                        }
                    }
                }

                $caseMap = $caseLinks->caseIdsForMessageIds(
                    $threadMessages->map(fn (MailMessageDto $m) => $m->internetMessageId)->all()
                );

                $existingCaseId = $caseMap[$anchor->normalizedMessageId() ?? ''] ?? null;
                if ($existingCaseId && $admin) {
                    $existingCase = CredentialingCase::find((int) $existingCaseId);
                    if ($existingCase) {
                        abort_unless($scope->canAccessCase($admin, $existingCase), 403);
                    }
                }

                if ($admin) {
                    foreach ($caseMap as $mappedCaseId) {
                        $mappedCase = CredentialingCase::find((int) $mappedCaseId);
                        if ($mappedCase) {
                            abort_unless($scope->canAccessCase($admin, $mappedCase), 403);
                        }
                    }
                }

                $anchor = $graph->getMessage($decodedMessageId, $this->folder, true);

                if (! $existingCaseId && $this->linkCaseId === '') {
                    $match = $matching->match($anchor->subject, $anchor->displayBody(), $anchor->fromAddress);
                    if ($match['case'] && (! $admin || $scope->canAccessCase($admin, $match['case']))) {
                        $this->linkCaseId = (string) $match['case']->id;
                    }
                } elseif ($existingCaseId && $this->linkCaseId === '') {
                    $this->linkCaseId = (string) $existingCaseId;
                }
            } catch (HttpException $e) {
                throw $e;
            } catch (\Throwable $e) {
                $this->loadError = self::sanitizeUtf8($e->getMessage());
            }
        }

        $linkedCases = [];
        if ($caseMap !== []) {
            $linkedCasesQuery = CredentialingCase::query()->whereIn('id', array_values($caseMap));
            if ($admin) {
                $scope->scopeCredentialingCases($linkedCasesQuery, $admin);
            }
            $linkedCases = $linkedCasesQuery
                ->get(['id', 'case_number'])
                ->keyBy('id');
        }

        $casesQuery = CredentialingCase::with('provider.user')->latest();
        if ($admin) {
            $scope->scopeCredentialingCases($casesQuery, $admin);
        }

        $providersQuery = ProviderDetails::with('user:id,name')->latest('id');
        if ($admin) {
            $scope->scopeProviders($providersQuery, $admin);
        }

        return view('livewire.admin.email.email-view-page', [
            'threadMessages' => $threadMessages,
            'anchor' => $anchor,
            'caseMap' => $caseMap,
            'linkedCases' => $linkedCases,
            'cases' => $casesQuery->limit(100)->get(['id', 'case_number', 'provider_id']),
            'providers' => $providersQuery->limit(100)->get(['id', 'user_id', 'npi']),
            'documentTypes' => DocumentType::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'templates' => NotificationTemplate::where('is_active', true)->orderBy('name')->get(),
            'smtpConfigured' => $mailSettings->isConfigured(),
            'canSend' => $this->canSendEmail(),
            'canManageMail' => $admin?->can('admin.settings.mail') ?? false,
            'canLink' => $this->canLinkEmail(),
            'canIngestAttachment' => $this->canIngestAttachment(),
        ]);
    }

    public static function encodeId(string $id): string
    {
        return rtrim(strtr(base64_encode($id), '+/', '-_'), '=');
    }

    public static function decodeId(string $value): string
    {
        $padded = strtr($value, '-_', '+/');
        $padded .= str_repeat('=', (4 - strlen($padded) % 4) % 4);
        $decoded = base64_decode($padded, true);

        if ($decoded === false || $decoded === '') {
            abort(404);
        }

        return $decoded;
    }

    public static function isValidEncodedId(string $value): bool
    {
        $padded = strtr($value, '-_', '+/');
        $padded .= str_repeat('=', (4 - strlen($padded) % 4) % 4);
        $decoded = base64_decode($padded, true);

        return $decoded !== false && $decoded !== '';
    }

    protected function assertAdminCanAccessMessage(
        Admin $admin,
        AdminScopeService $scope,
        EmailCaseLinkService $caseLinks,
        string $messageIdentifier,
    ): void {
        if ($messageIdentifier === '') {
            return;
        }

        $existingCase = $caseLinks->findCase($messageIdentifier);
        if ($existingCase) {
            abort_unless($scope->canAccessCase($admin, $existingCase), 403);
        }

        $emailMessage = EmailMessage::with(['credentialingCase', 'provider.practices'])
            ->where(function ($q) use ($messageIdentifier) {
                $q->where('external_message_id', $messageIdentifier)
                    ->orWhere('message_id', $messageIdentifier);
            })
            ->first();

        if ($emailMessage?->credentialingCase) {
            abort_unless($scope->canAccessCase($admin, $emailMessage->credentialingCase), 403);
        } elseif ($emailMessage?->provider) {
            abort_unless($scope->canAccessProvider($admin, $emailMessage->provider), 403);
        }
    }

    protected static function sanitizeUtf8(string $value): string
    {
        if ($value === '') {
            return '';
        }

        $clean = iconv('UTF-8', 'UTF-8//IGNORE', $value);

        return $clean !== false ? $clean : '';
    }
}
