<div class="container-fluid flex-grow-1 px-3 px-md-4 py-3 py-md-4">
    <style>
        .ev-wrap { max-width: 880px; margin: 0 auto; }
        .ev-top {
            background: #fff;
            border: 1px solid #e6e8ec;
            border-radius: 14px;
            padding: 1.1rem 1.35rem;
            margin-bottom: 1.25rem;
            box-shadow: 0 1px 3px rgba(16, 24, 40, 0.04);
        }
        .ev-subject {
            font-size: 1.2rem;
            font-weight: 700;
            color: #2f2b3d;
            line-height: 1.4;
            margin: 0.65rem 0 0.35rem;
            word-break: break-word;
        }
        .ev-submeta { color: #8592a3; font-size: 0.8125rem; }
        .ev-card {
            background: #fff;
            border: 1px solid #e6e8ec;
            border-radius: 14px;
            overflow: hidden;
            margin-bottom: 1.25rem;
            box-shadow: 0 4px 14px rgba(16, 24, 40, 0.06);
        }
        .ev-card-head {
            padding: 1.35rem 1.5rem 1.15rem;
            border-bottom: 1px solid #eef0f3;
            background: linear-gradient(180deg, #fbfbfc 0%, #fff 100%);
        }
        .ev-sender-row {
            display: flex;
            gap: 0.9rem;
            align-items: flex-start;
        }
        .ev-avatar {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 0.9rem;
            color: #fff;
            flex-shrink: 0;
        }
        .ev-avatar.in { background: linear-gradient(135deg, #03c3ec, #0d6efd); }
        .ev-avatar.out { background: linear-gradient(135deg, #696cff, #8592ff); }
        .ev-sender-name {
            font-weight: 700;
            color: #2f2b3d;
            font-size: 1rem;
            margin: 0;
            line-height: 1.3;
        }
        .ev-sender-email { color: #8592a3; font-size: 0.8125rem; }
        .ev-time {
            color: #8592a3;
            font-size: 0.8125rem;
            white-space: nowrap;
            margin-left: auto;
            padding-top: 0.15rem;
        }
        .ev-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            padding: 0.2rem 0.55rem;
            border-radius: 6px;
            margin-top: 0.4rem;
        }
        .ev-badge.in { background: #e7f8fd; color: #03c3ec; }
        .ev-badge.out { background: #e7e7ff; color: #696cff; }
        .ev-fields {
            margin-top: 1rem;
            padding: 0.85rem 1rem;
            background: #f8f9fb;
            border: 1px solid #eef0f3;
            border-radius: 10px;
        }
        .ev-field {
            display: grid;
            grid-template-columns: 64px 1fr;
            gap: 0.35rem 0.75rem;
            font-size: 0.85rem;
            padding: 0.28rem 0;
        }
        .ev-field + .ev-field { border-top: 1px dashed #e6e8ec; padding-top: 0.45rem; margin-top: 0.2rem; }
        .ev-field .k { color: #a1acb8; font-weight: 600; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.03em; padding-top: 0.1rem; }
        .ev-field .v { color: #566a7f; word-break: break-word; }
        .ev-field .v.strong { color: #2f2b3d; font-weight: 600; }
        .ev-body {
            padding: 1.6rem 1.75rem 1.75rem;
            font-size: 0.95rem;
            line-height: 1.75;
            color: #433f54;
            white-space: pre-wrap;
            word-break: break-word;
            font-family: "Segoe UI", system-ui, -apple-system, BlinkMacSystemFont, sans-serif;
            background: #fff;
        }
        .ev-body-empty { color: #a1acb8; font-style: italic; }
        .ev-attach {
            padding: 1rem 1.5rem 1.35rem;
            border-top: 1px solid #eef0f3;
            background: #fbfbfc;
        }
        .ev-attach-title {
            font-size: 0.72rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: #a1acb8;
            margin-bottom: 0.65rem;
        }
        .ev-chip {
            display: inline-flex;
            align-items: center;
            gap: 0.45rem;
            padding: 0.45rem 0.8rem;
            border: 1px solid #e6e8ec;
            border-radius: 8px;
            background: #fff;
            font-size: 0.8125rem;
            color: #566a7f;
            margin: 0 0.4rem 0.4rem 0;
            box-shadow: 0 1px 2px rgba(16,24,40,0.03);
        }
        .ev-sep {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            color: #a1acb8;
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            margin: 0.15rem 0 1rem;
        }
        .ev-sep::before, .ev-sep::after {
            content: "";
            flex: 1;
            height: 1px;
            background: #e6e8ec;
        }
        .ev-actions { display: flex; flex-wrap: wrap; gap: 0.5rem; margin-top: 0.85rem; }
        @media (max-width: 576px) {
            .ev-card-head, .ev-body { padding-left: 1.1rem; padding-right: 1.1rem; }
            .ev-sender-row { flex-wrap: wrap; }
            .ev-time { margin-left: 0; width: 100%; padding-left: 3.7rem; }
            .ev-field { grid-template-columns: 56px 1fr; }
        }
    </style>

    <div class="ev-wrap">
        <div class="ev-top">
            <a href="{{ route('admin.email.dashboard') }}" class="btn btn-sm btn-outline-secondary">
                <i class="ti tabler-arrow-left me-1"></i>Back to Email Center
            </a>

            <h1 class="ev-subject">{{ $email->subject ?: '(no subject)' }}</h1>
            <div class="ev-submeta">
                Thread · {{ $threadMessages->count() }} message{{ $threadMessages->count() === 1 ? '' : 's' }}
                @if ($email->credentialingCase)
                    · Linked to case
                    <span class="badge bg-label-secondary ms-1">{{ $email->credentialingCase->case_number }}</span>
                @endif
            </div>

            <div class="ev-actions">
                @if ($email->direction === 'inbound' && $canSend)
                    <button type="button" class="btn btn-primary" wire:click="openReplyModal">
                        <i class="ti tabler-arrow-back-up me-1"></i>Reply
                    </button>
                @endif
                @if ($canLink && ! $email->credentialing_case_id)
                    <button type="button" class="btn btn-outline-primary" wire:click="openLinkModal">
                        <i class="ti tabler-link me-1"></i>Link to Case
                    </button>
                @elseif ($email->credentialingCase)
                    <a href="{{ route('admin.credentials', ['search' => $email->credentialingCase->case_number]) }}"
                       class="btn btn-outline-secondary">
                        <i class="ti tabler-clipboard me-1"></i>Open Case
                    </a>
                @endif
            </div>
        </div>

        @foreach ($threadMessages as $msg)
            @if (! $loop->first)
                <div class="ev-sep">Earlier message</div>
            @endif

            <article class="ev-card" wire:key="full-msg-{{ $msg->id }}">
                <header class="ev-card-head">
                    <div class="ev-sender-row">
                        <div class="ev-avatar {{ $msg->direction === 'outbound' ? 'out' : 'in' }}">
                            {{ $msg->fromInitials() }}
                        </div>
                        <div class="flex-grow-1 min-w-0">
                            <p class="ev-sender-name">{{ $msg->fromDisplayName() }}</p>
                            <div class="ev-sender-email">{{ $msg->from_address }}</div>
                            <span class="ev-badge {{ $msg->direction === 'outbound' ? 'out' : 'in' }}">
                                <i class="ti tabler-{{ $msg->direction === 'outbound' ? 'send' : 'inbox' }}"></i>
                                {{ $msg->direction === 'outbound' ? 'Sent' : 'Received' }}
                            </span>
                        </div>
                        <time class="ev-time">
                            {{ ($msg->sent_at ?? $msg->received_at ?? $msg->created_at)?->format('M j, Y · g:i A') }}
                        </time>
                    </div>

                    <div class="ev-fields">
                        <div class="ev-field">
                            <span class="k">To</span>
                            <span class="v">{{ $msg->to_address }}</span>
                        </div>
                        @if ($msg->cc_address)
                            <div class="ev-field">
                                <span class="k">Cc</span>
                                <span class="v">{{ $msg->cc_address }}</span>
                            </div>
                        @endif
                        <div class="ev-field">
                            <span class="k">Subject</span>
                            <span class="v strong">{{ $msg->subject ?: '(no subject)' }}</span>
                        </div>
                    </div>
                </header>

                @php $bodyText = $msg->displayBody(); @endphp
                <div class="ev-body {{ $bodyText === '' ? 'ev-body-empty' : '' }}">
                    {{ $bodyText !== '' ? $bodyText : 'No message content.' }}
                </div>

                @if ($msg->attachments->isNotEmpty())
                    <div class="ev-attach">
                        <div class="ev-attach-title">
                            <i class="ti tabler-paperclip me-1"></i>
                            Attachments · {{ $msg->attachments->count() }}
                        </div>
                        <div>
                            @foreach ($msg->attachments as $attachment)
                                <span class="ev-chip">
                                    <i class="ti tabler-file"></i>
                                    {{ $attachment->original_name }}
                                    @if ($msg->credentialing_case_id)
                                        <button type="button" class="btn btn-link btn-sm p-0 text-primary"
                                            wire:click="saveAttachment({{ $attachment->id }})">
                                            Save to case
                                        </button>
                                    @endif
                                </span>
                            @endforeach
                        </div>
                    </div>
                @endif
            </article>
        @endforeach
    </div>

    @if ($showLinkModal)
        <div class="modal fade show d-block" tabindex="-1" style="background: rgba(0,0,0,.45);">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Link to Case</h5>
                        <button type="button" class="btn-close" wire:click="closeLinkModal"></button>
                    </div>
                    <div class="modal-body">
                        <label class="form-label">Credentialing Case</label>
                        <select wire:model="linkCaseId" class="form-select @error('linkCaseId') is-invalid @enderror">
                            <option value="">Select case...</option>
                            @foreach ($cases as $case)
                                <option value="{{ $case->id }}">{{ $case->case_number }} — {{ $case->provider->user->name ?? 'Provider' }}</option>
                            @endforeach
                        </select>
                        @error('linkCaseId')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" wire:click="closeLinkModal">Cancel</button>
                        <button type="button" class="btn btn-primary" wire:click="linkToCase">Link</button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    @if ($showComposeModal)
        @include('livewire.admin.email.partials.compose-modal', [
            'smtpConfigured' => $smtpConfigured,
            'canSend' => $canSend,
            'canManageMail' => $canManageMail,
            'templates' => $templates,
            'cases' => $cases,
        ])
    @endif
</div>
