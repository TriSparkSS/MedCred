<?php

namespace App\Livewire\Admin\Email;

use App\Data\MailMessageDto;
use App\Models\CredentialingCase;
use App\Models\NotificationTemplate;
use App\Services\CredentialingEmailService;
use App\Services\EmailCaseLinkService;
use App\Services\EmailMatchingService;
use App\Services\GraphMailboxService;
use App\Services\MailSettingsService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts::admin', ['title' => 'Email Conversation'])]
class EmailViewPage extends Component
{
    public string $folder = 'inbox';

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

    public function mount(string $folder, string $messageId): void
    {
        $this->folder = app(GraphMailboxService::class)->normalizeFolder($folder);
        $padded = strtr($messageId, '-_', '+/');
        $padded .= str_repeat('=', (4 - strlen($padded) % 4) % 4);
        $decoded = base64_decode($padded, true);
        if ($decoded === false || $decoded === '') {
            abort(404);
        }
        $this->messageId = $decoded;
    }

    public function openLinkModal(?string $internetMessageId = null): void
    {
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
        $this->validate([
            'linkCaseId' => 'required|exists:credentialing_cases,id',
            'linkMessageId' => 'required|string',
        ]);

        $case = CredentialingCase::findOrFail((int) $this->linkCaseId);
        $emailService->linkMessageToCase($this->linkMessageId, $case, Auth::guard('admin')->id());
        $this->closeLinkModal();
        flash()->success('Email linked to case '.$case->case_number);
    }

    public function linkEmail(CredentialingEmailService $emailService): void
    {
        $this->linkToCase($emailService);
    }

    public function openReplyModal(string $to, string $subject, string $inReplyTo = '', string $caseId = ''): void
    {
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
        $this->validate([
            'composeTo' => 'required|email',
            'composeSubject' => 'required|string|max:500',
            'composeBody' => 'required|string',
            'composeCaseId' => 'nullable|exists:credentialing_cases,id',
        ]);

        $mailSettings->assertConfigured();

        $case = $this->composeCaseId ? CredentialingCase::find((int) $this->composeCaseId) : null;
        $template = $this->composeTemplateId ? NotificationTemplate::find((int) $this->composeTemplateId) : null;

        if ($template && $case) {
            $result = $emailService->sendFromTemplate(
                $case,
                $template,
                $this->composeTo,
                Auth::guard('admin')->id(),
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
                Auth::guard('admin')->id(),
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

    public function render(
        GraphMailboxService $graph,
        EmailCaseLinkService $caseLinks,
        EmailMatchingService $matching,
        MailSettingsService $mailSettings,
    ) {
        /** @var Collection<int, MailMessageDto> $threadMessages */
        $threadMessages = collect();
        $this->loadError = null;
        $anchor = null;
        $caseMap = [];

        if (! $graph->isConfigured()) {
            $this->loadError = 'Microsoft Graph is not configured.';
        } else {
            try {
                $anchor = $graph->getMessage($this->messageId, $this->folder, true);
                $threadMessages = $graph->getConversation($anchor->conversationId, $this->messageId, $this->folder);
                $this->linkMessageId = $anchor->internetMessageId ?? '';

                $caseMap = $caseLinks->caseIdsForMessageIds(
                    $threadMessages->map(fn (MailMessageDto $m) => $m->internetMessageId)->all()
                );

                $existingCaseId = $caseMap[$anchor->normalizedMessageId() ?? ''] ?? null;
                if (! $existingCaseId && $this->linkCaseId === '') {
                    $match = $matching->match($anchor->subject, $anchor->displayBody(), $anchor->fromAddress);
                    if ($match['case']) {
                        $this->linkCaseId = (string) $match['case']->id;
                    }
                } elseif ($existingCaseId && $this->linkCaseId === '') {
                    $this->linkCaseId = (string) $existingCaseId;
                }
            } catch (\Throwable $e) {
                $this->loadError = $e->getMessage();
            }
        }

        $linkedCases = [];
        if ($caseMap !== []) {
            $linkedCases = CredentialingCase::query()
                ->whereIn('id', array_values($caseMap))
                ->get(['id', 'case_number'])
                ->keyBy('id');
        }

        return view('livewire.admin.email.email-view-page', [
            'threadMessages' => $threadMessages,
            'anchor' => $anchor,
            'caseMap' => $caseMap,
            'linkedCases' => $linkedCases,
            'cases' => CredentialingCase::with('provider.user')->latest()->limit(100)->get(['id', 'case_number', 'provider_id']),
            'templates' => NotificationTemplate::where('is_active', true)->orderBy('name')->get(),
            'smtpConfigured' => $mailSettings->isConfigured(),
            'canSend' => Auth::guard('admin')->user()?->can('admin.emails.send') ?? false,
            'canManageMail' => Auth::guard('admin')->user()?->can('admin.settings.manage') ?? false,
            'canLink' => Auth::guard('admin')->user()?->can('admin.emails.link') ?? false,
        ]);
    }

    public static function encodeId(string $id): string
    {
        return rtrim(strtr(base64_encode($id), '+/', '-_'), '=');
    }
}
