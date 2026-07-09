<div>
<div class="container-fluid flex-grow-1 px-3 px-md-4 py-3 py-md-4">

    @if (! $smtpConfigured)
        <div class="alert alert-warning d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
            <span><i class="ti tabler-alert-triangle me-1"></i> SMTP is not configured. Outbound email will fail until you save mail settings.</span>
            <a href="{{ route('admin.settings.mail') }}" class="btn btn-sm btn-warning">Configure SMTP</a>
        </div>
    @endif

    @if (! $imapConfigured)
        <div class="alert alert-info d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
            <span><i class="ti tabler-inbox me-1"></i> IMAP is not configured. Inbox sync is disabled until IMAP settings are saved.</span>
            <a href="{{ route('admin.settings.mail') }}" class="btn btn-sm btn-outline-primary">Configure IMAP</a>
        </div>
    @endif

    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body d-flex flex-column flex-md-row justify-content-between align-items-start gap-3">
            <div>
                <h3 class="fw-bold text-primary mb-1"><i class="ti tabler-mail me-2"></i>Email Center</h3>
                <p class="text-muted mb-0">Operational inbox for credentialing correspondence and SLA reminders.</p>
                @if ($imapLastSyncAt)
                    <small class="text-muted">Last mailbox sync: {{ \Carbon\Carbon::parse($imapLastSyncAt)->diffForHumans() }}</small>
                @endif
            </div>
            <div class="d-flex flex-wrap gap-2">
                <button type="button" wire:click="openComposeModal" class="btn btn-primary" title="{{ $canSend ? 'Compose a new email' : 'You do not have permission to send emails' }}">
                    <i class="ti tabler-send me-1"></i>Compose Email
                </button>
                <button type="button" wire:click="syncMailbox" class="btn btn-outline-secondary" wire:loading.attr="disabled" wire:target="syncMailbox" @if(! $imapConfigured) disabled @endif>
                    <span wire:loading.remove wire:target="syncMailbox"><i class="ti tabler-refresh me-1"></i>Sync Mailbox</span>
                    <span wire:loading wire:target="syncMailbox"><i class="ti tabler-loader me-1"></i>Syncing...</span>
                </button>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-xl-3">
            <div role="button" tabindex="0" wire:click="setFilter('sent')" class="card shadow-sm border-0 h-100 text-start {{ $filter === 'sent' ? 'border border-primary' : '' }}" style="cursor: pointer;">
                <div class="card-body">
                    <p class="text-uppercase text-muted small mb-1">Emails Sent</p>
                    <h3 class="fw-bold mb-0">{{ $stats['total_sent'] }}</h3>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div role="button" tabindex="0" wire:click="setFilter('inbox')" class="card shadow-sm border-0 h-100 text-start {{ $filter === 'inbox' ? 'border border-primary' : '' }}" style="cursor: pointer;">
                <div class="card-body">
                    <p class="text-uppercase text-muted small mb-1">Inbox</p>
                    <h3 class="fw-bold mb-0 text-info">{{ $stats['inbox_count'] }}</h3>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div role="button" tabindex="0" wire:click="setFilter('unlinked')" class="card shadow-sm border-0 h-100 text-start {{ $filter === 'unlinked' ? 'border border-primary' : '' }}" style="cursor: pointer;">
                <div class="card-body">
                    <p class="text-uppercase text-muted small mb-1">Unlinked Inbound</p>
                    <h3 class="fw-bold mb-0 text-warning">{{ $stats['unlinked_inbound'] }}</h3>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div role="button" tabindex="0" wire:click="setFilter('failed')" class="card shadow-sm border-0 h-100 text-start {{ $filter === 'failed' ? 'border border-primary' : '' }}" style="cursor: pointer;">
                <div class="card-body">
                    <p class="text-uppercase text-muted small mb-1">Failed / Bounced</p>
                    <h3 class="fw-bold mb-0 text-danger">{{ $stats['bounced_failed'] }}</h3>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-5">
                    <div class="input-group">
                        <span class="input-group-text bg-white border-end-0"><i class="ti tabler-search"></i></span>
                        <input type="search" wire:model.live.debounce.300ms="search" class="form-control border-start-0"
                            placeholder="Search subject, addresses, case #...">
                    </div>
                </div>
                <div class="col-md-4">
                    <select wire:model.live="filter" class="form-select">
                        <option value="all">All Messages</option>
                        <option value="inbox">Inbox</option>
                        <option value="sent">Sent</option>
                        <option value="unlinked">Unlinked</option>
                        <option value="provider_responses">Provider Responses</option>
                        <option value="payer_responses">Payer Responses</option>
                        <option value="attachments_pending">Attachments Pending</option>
                        <option value="replies_awaited">Replies Awaited</option>
                        <option value="escalation">Escalation</option>
                        <option value="failed">Failed / Bounced</option>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm border-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Direction</th>
                        <th>Subject</th>
                        <th>Case</th>
                        <th>To / From</th>
                        <th>Status</th>
                        <th>Date</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($emails as $email)
                        <tr wire:key="email-{{ $email->id }}">
                            <td>
                                <span class="badge bg-label-{{ $email->direction === 'outbound' ? 'primary' : 'info' }}">
                                    {{ ucfirst($email->direction) }}
                                </span>
                            </td>
                            <td>
                                <div class="fw-medium">{{ Str::limit($email->subject, 50) }}</div>
                                @if ($email->notificationTemplate)
                                    <small class="text-muted">{{ $email->notificationTemplate->name }}</small>
                                @endif
                            </td>
                            <td>
                                @if ($email->credentialingCase)
                                    {{ $email->credentialingCase->case_number }}
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td class="small">
                                @if ($email->direction === 'outbound')
                                    To: {{ $email->to_address }}
                                @else
                                    From: {{ $email->from_address }}
                                @endif
                            </td>
                            <td>
                                @php
                                    $statusClass = match ($email->status) {
                                        'sent', 'received' => 'success',
                                        'failed', 'bounced' => 'danger',
                                        default => 'secondary',
                                    };
                                @endphp
                                <span class="badge bg-label-{{ $statusClass }}">{{ ucfirst($email->status) }}</span>
                                @if ($email->status === 'failed' && $email->error_message)
                                    <div class="small text-danger mt-1" title="{{ $email->error_message }}">{{ Str::limit($email->error_message, 40) }}</div>
                                @endif
                            </td>
                            <td class="small text-muted">
                                {{ ($email->sent_at ?? $email->received_at ?? $email->created_at)?->format('m/d/Y g:i A') }}
                            </td>
                            <td class="text-end">
                                <div class="btn-group btn-group-sm">
                                    <button type="button" wire:click="openThread({{ $email->id }})" class="btn btn-outline-secondary" title="View thread">
                                        <i class="ti tabler-messages"></i>
                                    </button>
                                    @if ($email->direction === 'inbound' && $canSend)
                                        <button type="button" wire:click="openReplyModal({{ $email->id }})" class="btn btn-outline-primary" title="Reply">
                                            <i class="ti tabler-arrow-back-up"></i>
                                        </button>
                                    @endif
                                    @if (! $email->credentialing_case_id)
                                        <button type="button" wire:click="openLinkModal({{ $email->id }})" class="btn btn-outline-primary" title="Link to case">
                                            <i class="ti tabler-link"></i>
                                        </button>
                                    @endif
                                    @foreach ($email->attachments as $attachment)
                                        <button type="button" wire:click="importAttachment({{ $attachment->id }})"
                                            class="btn btn-outline-secondary" title="Import attachment">
                                            <i class="ti tabler-file-import"></i>
                                        </button>
                                    @endforeach
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted py-5">No emails yet. Compose a message, send a test from Mail Settings, or sync the mailbox.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer bg-white">{{ $emails->links('livewire::bootstrap') }}</div>
    </div>

    @if ($showThreadDrawer)
        <div class="offcanvas offcanvas-end show" tabindex="-1" style="visibility: visible; z-index: 1090;" wire:key="email-thread-drawer">
            <div class="offcanvas-header border-bottom">
                <h5 class="offcanvas-title">Email Thread</h5>
                <button type="button" class="btn-close" wire:click="closeThread" aria-label="Close"></button>
            </div>
            <div class="offcanvas-body">
                @forelse ($threadMessages as $msg)
                    <div class="border rounded p-3 mb-3 {{ $msg->direction === 'outbound' ? 'bg-label-primary' : 'bg-label-info' }}" wire:key="thread-msg-{{ $msg->id }}">
                        <div class="d-flex justify-content-between small text-muted mb-2">
                            <span>{{ $msg->direction === 'outbound' ? 'To: ' . $msg->to_address : 'From: ' . $msg->from_address }}</span>
                            <span>{{ ($msg->sent_at ?? $msg->received_at ?? $msg->created_at)?->format('m/d/Y g:i A') }}</span>
                        </div>
                        <h6 class="fw-semibold">{{ $msg->subject }}</h6>
                        <div class="small" style="white-space: pre-wrap;">{{ $msg->body }}</div>
                        @if ($msg->attachments->isNotEmpty())
                            <div class="mt-2">
                                @foreach ($msg->attachments as $attachment)
                                    <span class="badge bg-secondary me-1">{{ $attachment->original_name }}</span>
                                @endforeach
                            </div>
                        @endif
                        @if ($msg->direction === 'inbound' && $canSend)
                            <button type="button" wire:click="openReplyModal({{ $msg->id }})" class="btn btn-sm btn-primary mt-2">
                                <i class="ti tabler-arrow-back-up me-1"></i>Reply
                            </button>
                        @endif
                    </div>
                @empty
                    <p class="text-muted">No messages in this thread.</p>
                @endforelse
            </div>
        </div>
        <div class="offcanvas-backdrop fade show" wire:click="closeThread" style="z-index: 1085;"></div>
    @endif

    @if ($showLinkModal)
        <div class="modal fade show d-block" tabindex="-1" style="background: rgba(0,0,0,.5); z-index: 1090;" wire:click.self="closeLinkModal" wire:keydown.escape.window="closeLinkModal" wire:key="email-link-modal">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Link Email to Case</h5>
                        <button type="button" class="btn-close" wire:click="closeLinkModal" aria-label="Close"></button>
                    </div>
                    <form wire:submit.prevent="linkEmail">
                        <div class="modal-body">
                            <label class="form-label">Credentialing Case</label>
                            <select wire:model="linkCaseId" class="form-select @error('linkCaseId') is-invalid @enderror">
                                <option value="">Select case...</option>
                                @foreach ($cases as $case)
                                    <option value="{{ $case->id }}">{{ $case->case_number }} — {{ $case->provider->user->name ?? '' }}</option>
                                @endforeach
                            </select>
                            @error('linkCaseId')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" wire:click="closeLinkModal">Cancel</button>
                            <button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:target="linkEmail">
                                <span wire:loading.remove wire:target="linkEmail">Link Email</span>
                                <span wire:loading wire:target="linkEmail">Linking...</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif

    @if ($showComposeModal)
        <div class="modal fade show d-block" tabindex="-1" style="background: rgba(0,0,0,.5); z-index: 1090;" wire:click.self="closeComposeModal" wire:keydown.escape.window="closeComposeModal" wire:key="email-compose-modal">
            <div class="modal-dialog modal-dialog-centered modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">{{ $composeInReplyTo ? 'Reply' : 'Compose Email' }}</h5>
                        <button type="button" class="btn-close" wire:click="closeComposeModal" aria-label="Close"></button>
                    </div>
                    <form wire:submit.prevent="sendEmail">
                        <div class="modal-body">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label" for="composeCaseId">Case (optional)</label>
                                    <select id="composeCaseId" wire:model.live="composeCaseId" class="form-select">
                                        <option value="">No case</option>
                                        @foreach ($cases as $case)
                                            <option value="{{ $case->id }}">{{ $case->case_number }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="composeTemplateId">Template</label>
                                    <select id="composeTemplateId" wire:model.live="composeTemplateId" class="form-select" @if($composeInReplyTo) disabled @endif>
                                        <option value="">Custom message</option>
                                        @foreach ($templates as $template)
                                            <option value="{{ $template->id }}">{{ $template->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-12">
                                    <label class="form-label" for="composeTo">To <span class="text-danger">*</span></label>
                                    <input id="composeTo" type="email" wire:model="composeTo" class="form-control @error('composeTo') is-invalid @enderror" autocomplete="email">
                                    @error('composeTo')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-12">
                                    <label class="form-label" for="composeSubject">Subject <span class="text-danger">*</span></label>
                                    <input id="composeSubject" type="text" wire:model="composeSubject" class="form-control @error('composeSubject') is-invalid @enderror">
                                    @error('composeSubject')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-12">
                                    <label class="form-label" for="composeBody">Body <span class="text-danger">*</span></label>
                                    <textarea id="composeBody" wire:model="composeBody" rows="8" class="form-control @error('composeBody') is-invalid @enderror"></textarea>
                                    @error('composeBody')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" wire:click="closeComposeModal">Cancel</button>
                            <button type="submit" class="btn btn-primary" @disabled(! $smtpConfigured) wire:loading.attr="disabled" wire:target="sendEmail">
                                <span wire:loading.remove wire:target="sendEmail">Send Email</span>
                                <span wire:loading wire:target="sendEmail">Sending...</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
</div>
</div>
