<div class="container-fluid flex-grow-1 px-3 px-md-4 py-3 py-md-4">

    @if ($notification)
        <div class="alert alert-{{ $notification['type'] }} alert-dismissible fade show mb-4" role="alert">
            {{ $notification['message'] }}
            <button type="button" class="btn-close" wire:click="dismissNotification" aria-label="Close"></button>
        </div>
    @endif

    @if (! $smtpConfigured)
        <div class="alert alert-warning d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
            <span><i class="ti tabler-alert-triangle me-1"></i> SMTP is not configured. Outbound email will fail until you save mail settings.</span>
            <a href="{{ route('admin.settings.mail') }}" class="btn btn-sm btn-warning">Configure SMTP</a>
        </div>
    @endif

    @if (! $imapConfigured)
        <div class="alert alert-info d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
            <span><i class="ti tabler-inbox me-1"></i> IMAP is not configured. Mailbox sync is disabled until IMAP settings are saved.</span>
            <a href="{{ route('admin.settings.mail') }}" class="btn btn-sm btn-outline-primary">Configure IMAP</a>
        </div>
    @endif

    {{-- Header --}}
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body">
            <div class="d-flex flex-column flex-lg-row justify-content-between align-items-start gap-3">
                <div>
                    <h3 class="fw-bold text-primary mb-1"><i class="ti tabler-mail me-2"></i>Email Center</h3>
                    <p class="text-muted mb-0">Send credentialing emails, track threads, and sync your Office 365 mailbox.</p>
                    @if ($imapLastSyncAt)
                        <small class="text-muted d-block mt-1">Last sync: {{ \Carbon\Carbon::parse($imapLastSyncAt)->diffForHumans() }}</small>
                    @endif
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <button type="button" class="btn btn-primary" wire:click="openComposeModal" @disabled(! $canSend) title="{{ $canSend ? 'Compose email' : 'No send permission' }}">
                        <i class="ti tabler-pencil me-1"></i>Compose
                    </button>
                    <button type="button" class="btn btn-outline-secondary" wire:click="syncMailbox" wire:loading.attr="disabled" wire:target="syncMailbox" @disabled(! $imapConfigured || $syncing)>
                        <span wire:loading.remove wire:target="syncMailbox"><i class="ti tabler-refresh me-1"></i>Sync Mailbox</span>
                        <span wire:loading wire:target="syncMailbox"><i class="ti tabler-loader-2 me-1"></i>Syncing...</span>
                    </button>
                    <a href="{{ route('admin.settings.mail') }}" class="btn btn-outline-primary">
                        <i class="ti tabler-settings me-1"></i>Mail Settings
                    </a>
                </div>
            </div>
        </div>
    </div>

    {{-- Stats --}}
    <div class="row g-3 mb-4">
        @foreach ([
            ['key' => 'all', 'label' => 'All Messages', 'value' => $stats['total_sent'] + $stats['inbox_count'], 'class' => 'text-body'],
            ['key' => 'sent', 'label' => 'Sent', 'value' => $stats['total_sent'], 'class' => 'text-primary'],
            ['key' => 'inbox', 'label' => 'Inbox', 'value' => $stats['inbox_count'], 'class' => 'text-info'],
            ['key' => 'failed', 'label' => 'Failed', 'value' => $stats['bounced_failed'], 'class' => 'text-danger'],
        ] as $stat)
            <div class="col-6 col-xl-3">
                <button type="button" wire:click="setFilter('{{ $stat['key'] }}')"
                    class="card shadow-sm border-0 h-100 w-100 text-start {{ $filter === $stat['key'] ? 'border border-primary border-2' : '' }}">
                    <div class="card-body py-3">
                        <p class="text-uppercase text-muted small mb-1">{{ $stat['label'] }}</p>
                        <h3 class="fw-bold mb-0 {{ $stat['class'] }}">{{ $stat['value'] }}</h3>
                    </div>
                </button>
            </div>
        @endforeach
    </div>

    {{-- Filters --}}
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body">
            <div class="row g-3 align-items-end">
                <div class="col-lg-5">
                    <label class="form-label small text-muted mb-1">Search</label>
                    <div class="input-group">
                        <span class="input-group-text bg-white"><i class="ti tabler-search"></i></span>
                        <input type="search" wire:model.live.debounce.400ms="search" class="form-control" placeholder="Subject, addresses, case number...">
                    </div>
                </div>
                <div class="col-lg-4">
                    <label class="form-label small text-muted mb-1">Queue</label>
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
                <div class="col-lg-3">
                    @if ($search !== '' || $filter !== 'all')
                        <button type="button" wire:click="resetFilters" class="btn btn-outline-secondary w-100">
                            <i class="ti tabler-filter-off me-1"></i>Clear Filters
                        </button>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- Message list --}}
    <div class="card shadow-sm border-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Direction</th>
                        <th>Subject</th>
                        <th>Case</th>
                        <th>Contact</th>
                        <th>Status</th>
                        <th>Date</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($emails as $email)
                        <tr wire:key="email-row-{{ $email->id }}">
                            <td>
                                <span class="badge bg-label-{{ $email->direction === 'outbound' ? 'primary' : 'info' }}">
                                    {{ $email->direction === 'outbound' ? 'Sent' : 'Received' }}
                                </span>
                            </td>
                            <td>
                                <div class="fw-medium">{{ \Illuminate\Support\Str::limit($email->subject, 55) ?: '(no subject)' }}</div>
                                @if ($email->notificationTemplate)
                                    <small class="text-muted">{{ $email->notificationTemplate->name }}</small>
                                @endif
                            </td>
                            <td>
                                @if ($email->credentialingCase)
                                    <span class="badge bg-label-secondary">{{ $email->credentialingCase->case_number }}</span>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td class="small">
                                @if ($email->direction === 'outbound')
                                    <span class="text-muted">To</span> {{ $email->to_address }}
                                @else
                                    <span class="text-muted">From</span> {{ $email->from_address }}
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
                                    <div class="small text-danger mt-1" title="{{ $email->error_message }}">{{ \Illuminate\Support\Str::limit($email->error_message, 35) }}</div>
                                @endif
                            </td>
                            <td class="small text-nowrap text-muted">
                                {{ ($email->sent_at ?? $email->received_at ?? $email->created_at)?->format('m/d/Y g:i A') }}
                            </td>
                            <td class="text-end">
                                <div class="btn-group btn-group-sm">
                                    <button type="button" class="btn btn-outline-secondary" wire:click="openThread({{ $email->id }})" title="View thread">
                                        <i class="ti tabler-messages"></i>
                                    </button>
                                    @if ($email->direction === 'inbound' && $canSend)
                                        <button type="button" class="btn btn-outline-primary" wire:click="openReplyModal({{ $email->id }})" title="Reply">
                                            <i class="ti tabler-arrow-back-up"></i>
                                        </button>
                                    @endif
                                    @if (! $email->credentialing_case_id)
                                        <button type="button" class="btn btn-outline-primary" wire:click="openLinkModal({{ $email->id }})" title="Link to case">
                                            <i class="ti tabler-link"></i>
                                        </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center py-5">
                                <i class="ti tabler-mail-off d-block fs-1 text-muted mb-2"></i>
                                <p class="text-muted mb-2">No emails in this view.</p>
                                <div class="d-flex flex-wrap justify-content-center gap-2">
                                    @if ($canSend)
                                        <button type="button" class="btn btn-sm btn-primary" wire:click="openComposeModal">Compose Email</button>
                                    @endif
                                    @if ($imapConfigured)
                                        <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="syncMailbox">Sync Mailbox</button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($emails->hasPages())
            <div class="card-footer bg-white border-top">{{ $emails->links() }}</div>
        @endif
    </div>

    @include('livewire.admin.email.partials.thread-drawer')
    @include('livewire.admin.email.partials.link-modal')
    @include('livewire.admin.email.partials.compose-modal')
</div>
