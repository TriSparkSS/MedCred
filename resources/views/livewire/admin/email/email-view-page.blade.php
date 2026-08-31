<div class="container-fluid flex-grow-1 px-3 px-md-4 py-3 py-md-4">
    <style>
        .ev-wrap {
            width: 100%;
            max-width: none;
            margin: 0;
            display: flex;
            flex-direction: column;
            min-height: calc(100vh - 10rem);
        }
        .ev-topbar {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 0.85rem;
        }
        .ev-header {
            background: #fff;
            border: 1px solid #e8eaef;
            border-radius: 16px 16px 0 0;
            padding: 1rem 1.25rem;
            border-bottom: 0;
        }
        .ev-subject {
            font-size: 1.05rem;
            font-weight: 700;
            color: #2f2b3d;
            margin: 0 0 0.25rem;
            line-height: 1.35;
            word-break: break-word;
        }
        .ev-submeta {
            color: #8592a3;
            font-size: 0.8125rem;
        }
        .ev-chat-shell {
            background: linear-gradient(180deg, #f4f5f9 0%, #eef0f5 100%);
            border: 1px solid #e8eaef;
            border-top: 0;
            border-radius: 0 0 16px 16px;
            display: flex;
            flex-direction: column;
            flex: 1;
            min-height: 420px;
            overflow: hidden;
        }
        .ev-thread {
            flex: 1;
            overflow-y: auto;
            padding: 1.25rem 1.5rem 0.75rem;
            display: flex;
            flex-direction: column;
            gap: 0.85rem;
        }
        .ev-row {
            display: flex;
            align-items: flex-end;
            gap: 0.55rem;
            max-width: 100%;
        }
        .ev-row.in { justify-content: flex-start; }
        .ev-row.out { justify-content: flex-end; }
        .ev-avatar {
            width: 34px;
            height: 34px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 0.72rem;
            color: #fff;
            flex-shrink: 0;
            margin-bottom: 1.15rem;
        }
        .ev-avatar.in { background: linear-gradient(135deg, #03c3ec, #0d6efd); }
        .ev-avatar.out { background: linear-gradient(135deg, #696cff, #8592ff); }
        .ev-row.out .ev-avatar { order: 2; }
        .ev-bubble-wrap {
            max-width: min(78%, 920px);
            display: flex;
            flex-direction: column;
            gap: 0.2rem;
        }
        .ev-row.out .ev-bubble-wrap { align-items: flex-end; }
        .ev-row.in .ev-bubble-wrap { align-items: flex-start; }
        .ev-name-line {
            display: flex;
            align-items: baseline;
            gap: 0.45rem;
            padding: 0 0.35rem;
            font-size: 0.75rem;
            color: #8592a3;
        }
        .ev-row.out .ev-name-line { flex-direction: row-reverse; }
        .ev-name-line strong {
            color: #5d596c;
            font-weight: 600;
            font-size: 0.78rem;
        }
        .ev-bubble {
            border-radius: 18px;
            padding: 0.75rem 0.95rem;
            box-shadow: 0 1px 2px rgba(16, 24, 40, 0.05);
            position: relative;
        }
        .ev-bubble.in {
            background: #fff;
            border: 1px solid #e6e8ec;
            border-bottom-left-radius: 6px;
        }
        .ev-bubble.out {
            background: #091572;
            color: #fff;
            border-bottom-right-radius: 6px;
        }
        .ev-bubble.out .ev-body,
        .ev-bubble.out .ev-body a {
            color: #f5f6ff;
        }
        .ev-bubble.out .ev-quote {
            border-left-color: rgba(255,255,255,0.35);
            color: rgba(255,255,255,0.82);
        }
        .ev-body {
            font-size: 0.9rem;
            line-height: 1.55;
            white-space: pre-wrap;
            word-break: break-word;
            margin: 0;
        }
        .ev-body-html {
            white-space: normal;
            background: #fff;
            color: #2f2b3d;
            border-radius: 10px;
            padding: 0.75rem 0.85rem;
            border: 1px solid rgba(0, 0, 0, 0.06);
        }
        .ev-body-html a {
            color: #0d6efd;
        }
        .ev-body-html img {
            max-width: 100%;
            height: auto;
            border-radius: 8px;
            margin: 0.35rem 0;
        }
        .ev-body-html table {
            max-width: 100%;
            border-collapse: collapse;
        }
        .ev-body-html td,
        .ev-body-html th {
            color: #2f2b3d;
            padding: 0.35rem 0.5rem;
            vertical-align: top;
        }
        /* Forwarded Outlook tables often use navy label cells with dark text — fix contrast */
        .ev-body-html td[style*="091572"],
        .ev-body-html th[style*="091572"],
        .ev-body-html td[style*="rgb(9, 21, 114)"],
        .ev-body-html th[style*="rgb(9, 21, 114)"],
        .ev-body-html td[bgcolor="#091572"],
        .ev-body-html th[bgcolor="#091572"],
        .ev-body-html td[bgcolor="#091572" i],
        .ev-body-html th[bgcolor="#091572" i],
        .ev-body-html tr[style*="091572"] > td,
        .ev-body-html tr[style*="091572"] > th {
            color: #fff !important;
        }
        .ev-bubble.out .ev-body-html a {
            color: #0d6efd;
        }
        .ev-body :where(p, ul, ol) { margin-bottom: 0.5rem; }
        .ev-body :where(p:last-child, ul:last-child, ol:last-child) { margin-bottom: 0; }
        .ev-quote {
            margin-top: 0.65rem;
            padding-left: 0.65rem;
            border-left: 2px solid #d9dde7;
            color: #8592a3;
            font-size: 0.82rem;
        }
        .ev-attach {
            margin-top: 0.65rem;
            display: flex;
            flex-wrap: wrap;
            gap: 0.4rem;
        }
        .ev-bubble.out .ev-attach .btn {
            border-color: rgba(255,255,255,0.35);
            color: #fff;
        }
        .ev-bubble.out .ev-attach .btn:hover {
            background: rgba(255,255,255,0.12);
            color: #fff;
        }
        .ev-meta-chips {
            display: flex;
            flex-wrap: wrap;
            gap: 0.35rem;
            margin-top: 0.45rem;
        }
        .ev-chip {
            font-size: 0.68rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            padding: 0.12rem 0.45rem;
            border-radius: 999px;
            background: #eef0f4;
            color: #6f6b7d;
        }
        .ev-bubble.out .ev-chip {
            background: rgba(255,255,255,0.16);
            color: #fff;
        }
        .ev-chip.case { background: #e8fadf; color: #28c76f; }
        .ev-bubble.out .ev-chip.case { background: rgba(40, 199, 111, 0.25); color: #b8f0d0; }
        .ev-reply-bar {
            background: #fff;
            border-top: 1px solid #e8eaef;
            padding: 0.85rem 1rem;
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 0.65rem;
        }
        .ev-reply-hint {
            flex: 1;
            min-width: 180px;
            color: #8592a3;
            font-size: 0.875rem;
            padding: 0.65rem 0.9rem;
            background: #f5f5f9;
            border: 1px dashed #d9dde7;
            border-radius: 12px;
            cursor: pointer;
            transition: background 0.15s ease, border-color 0.15s ease;
        }
        .ev-reply-hint:hover {
            background: #eef0ff;
            border-color: #c5caf5;
            color: #5d596c;
        }
        @media (max-width: 576px) {
            .ev-bubble-wrap { max-width: 92%; }
            .ev-thread { padding-inline: 0.75rem; }
            .ev-avatar { display: none; }
        }
    </style>

    <div class="ev-wrap">
        <div class="ev-topbar">
            <a href="{{ route('admin.email.dashboard') }}" class="btn btn-outline-secondary btn-sm">
                <i class="ti tabler-arrow-left me-1"></i>Back to Email Center
            </a>
            @if ($anchor?->internetMessageId && ($canLink ?? false))
                <button type="button" class="btn btn-outline-secondary btn-sm" wire:click="openLinkModal({{ json_encode($anchor->internetMessageId) }})">
                    <i class="ti tabler-link me-1"></i>Link to Case
                </button>
            @endif
        </div>

        @if ($loadError)
            <div class="alert alert-danger">{{ $loadError }}</div>
        @elseif ($anchor)
            @php
                $latest = $threadMessages->last() ?? $anchor;
                $replyTo = $latest->direction === 'inbound' ? $latest->fromAddress : $latest->toAddress;
                $replyCase = $caseMap[$latest->normalizedMessageId() ?? ''] ?? ($caseMap[$anchor->normalizedMessageId() ?? ''] ?? '');
                $replySubject = $anchor->subject;
                $replyInReplyTo = $latest->internetMessageId ?? $anchor->internetMessageId ?? '';
                $primaryCaseId = $caseMap[$anchor->normalizedMessageId() ?? ''] ?? null;
            @endphp

            <div class="ev-header">
                <h1 class="ev-subject">{{ $anchor->subject }}</h1>
                <div class="ev-submeta">
                    {{ $threadMessages->count() }} message{{ $threadMessages->count() === 1 ? '' : 's' }}
                    @if ($primaryCaseId && isset($linkedCases[$primaryCaseId]))
                        · Linked to <span class="badge bg-label-success">{{ $linkedCases[$primaryCaseId]->case_number }}</span>
                    @endif
                </div>
            </div>

            <div class="ev-chat-shell">
                <div class="ev-thread" id="ev-thread-scroll">
                    @foreach ($threadMessages as $msg)
                        @php
                            $dir = $msg->direction === 'outbound' ? 'out' : 'in';
                            $norm = $msg->normalizedMessageId();
                            $msgCaseId = $norm ? ($caseMap[$norm] ?? null) : null;
                            $encodedMsg = \App\Livewire\Admin\Email\EmailViewPage::encodeId($msg->id);
                        @endphp
                        <div class="ev-row {{ $dir }}" wire:key="thread-{{ $msg->id }}">
                            <div class="ev-avatar {{ $dir }}">{{ $msg->fromInitials() }}</div>
                            <div class="ev-bubble-wrap">
                                <div class="ev-name-line">
                                    <strong>{{ $msg->fromName ?: $msg->fromAddress }}</strong>
                                    <span>{{ $msg->date?->timezone(config('app.timezone'))->format('M j, g:i A') }}</span>
                                </div>
                                <div class="ev-bubble {{ $dir }}">
                                    <div class="ev-body">
                                        @php
                                            $resolvedHtml = $msg->bodyHtmlWithInlineAttachments($msg->folder ?: $folder, $encodedMsg);
                                        @endphp
                                        @if (filled($resolvedHtml))
                                            <div class="ev-body-html" wire:ignore>{!! $resolvedHtml !!}</div>
                                        @elseif (filled($msg->bodyHtml))
                                            <div class="ev-body-html" wire:ignore>{!! \App\Data\MailMessageDto::sanitizeHtmlForDisplay($msg->bodyHtml) !!}</div>
                                        @else
                                            {{ $msg->displayBody() ?: '—' }}
                                        @endif
                                    </div>

                                    @if ($msg->attachments !== [])
                                        <div class="ev-attach d-flex flex-wrap gap-2">
                                            @foreach ($msg->attachments as $attachment)
                                                @if (($attachment['isInline'] ?? false) && ($attachment['kind'] ?? 'file') === 'file')
                                                    @continue
                                                @endif
                                                @if (! empty($attachment['id']) || ! empty($attachment['sourceUrl']))
                                                    @php
                                                        $attachFolder = $msg->folder ?: $folder;
                                                        $encodedAttach = ! empty($attachment['id'])
                                                            ? \App\Livewire\Admin\Email\EmailViewPage::encodeId($attachment['id'])
                                                            : null;
                                                        $attachRoute = $encodedAttach ? [
                                                            'folder' => $attachFolder,
                                                            'messageId' => $encodedMsg,
                                                            'attachmentId' => $encodedAttach,
                                                        ] : null;
                                                        $contentType = $attachment['contentType'] ?? null;
                                                        $size = $attachment['size'] ?? null;
                                                        $metaBits = array_filter([
                                                            $contentType,
                                                            is_numeric($size) ? number_format(((int) $size) / 1024, 1).' KB' : null,
                                                        ]);
                                                        $isReference = ($attachment['kind'] ?? 'file') === 'reference';
                                                    @endphp
                                                    <div class="d-inline-flex align-items-center gap-1 flex-wrap">
                                                        @if ($isReference && ! empty($attachment['sourceUrl']))
                                                            <a class="btn btn-sm btn-outline-secondary"
                                                                target="_blank"
                                                                rel="noopener"
                                                                href="{{ $attachment['sourceUrl'] }}"
                                                                title="Open {{ $attachment['name'] }} in cloud storage">
                                                                <i class="ti tabler-cloud me-1"></i>{{ $attachment['name'] }}
                                                                @if ($metaBits)
                                                                    <span class="text-muted ms-1 small">{{ implode(' · ', $metaBits) }}</span>
                                                                @endif
                                                            </a>
                                                        @elseif ($attachRoute)
                                                        <a class="btn btn-sm btn-outline-secondary"
                                                            target="_blank"
                                                            rel="noopener"
                                                            href="{{ route('admin.email.attachment', $attachRoute) }}"
                                                            title="{{ $attachment['name'] }}{{ $metaBits ? ' ('.implode(' · ', $metaBits).')' : '' }}">
                                                            <i class="ti tabler-paperclip me-1"></i>{{ $attachment['name'] }}
                                                            @if ($metaBits)
                                                                <span class="text-muted ms-1 small">{{ implode(' · ', $metaBits) }}</span>
                                                            @endif
                                                        </a>
                                                        <a class="btn btn-sm btn-link px-1"
                                                            href="{{ route('admin.email.attachment', $attachRoute) }}?download=1"
                                                            title="Download {{ $attachment['name'] }}">
                                                            Download
                                                        </a>
                                                        @endif
                                                    </div>
                                                @endif
                                            @endforeach
                                        </div>
                                    @endif

                                    <div class="ev-meta-chips">
                                        <span class="ev-chip">{{ $msg->direction === 'outbound' ? 'Sent' : 'Received' }}</span>
                                        @if ($msgCaseId && isset($linkedCases[$msgCaseId]))
                                            <span class="ev-chip case">{{ $linkedCases[$msgCaseId]->case_number }}</span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                @if ($canSend ?? false)
                    <div class="ev-reply-bar">
                        <button type="button"
                            class="ev-reply-hint text-start border-0"
                            wire:click="openReplyModal({{ json_encode($replyTo) }}, {{ json_encode($replySubject) }}, {{ json_encode($replyInReplyTo) }}, {{ json_encode((string) $replyCase) }})">
                            <i class="ti tabler-message me-1"></i>Reply to this conversation…
                        </button>
                        <button type="button"
                            class="btn btn-primary"
                            wire:click="openReplyModal({{ json_encode($replyTo) }}, {{ json_encode($replySubject) }}, {{ json_encode($replyInReplyTo) }}, {{ json_encode((string) $replyCase) }})">
                            <i class="ti tabler-arrow-back-up me-1"></i>Reply
                        </button>
                    </div>
                @endif
            </div>
        @endif
    </div>

    @include('livewire.admin.email.partials.compose-modal')
    @include('livewire.admin.email.partials.link-modal')
</div>
