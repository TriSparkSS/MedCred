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
            @if ($canManageMail ?? false)
                <a href="{{ route('admin.settings.mail') }}" class="btn btn-sm btn-warning">Configure SMTP</a>
            @endif
        </div>
    @endif

    @if (! $graphConfigured)
        <div class="alert alert-info d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
            <span><i class="ti tabler-inbox me-1"></i> Email Center reads live from Microsoft Graph. Set <code>GRAPH_TENANT_ID</code>, <code>GRAPH_CLIENT_ID</code>, <code>GRAPH_CLIENT_SECRET</code>, and <code>GRAPH_MAILBOX</code>.</span>
            @if ($canManageMail ?? false)
                <a href="{{ route('admin.settings.mail') }}" class="btn btn-sm btn-outline-primary">Mail Settings</a>
            @endif
        </div>
    @else
        <div class="alert alert-success d-flex flex-wrap align-items-center gap-2 mb-4 py-2">
            <span>
                <i class="ti tabler-cloud me-1"></i>
                Live mailbox via Microsoft Graph
                @if ($graphMailbox)
                    (<code>{{ $graphMailbox }}</code>)
                @endif
                — messages are not stored in the CRM database.
            </span>
        </div>
    @endif

    @if ($mailboxError)
        <div class="alert alert-danger mb-4">{{ $mailboxError }}</div>
    @endif

    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body">
            <div class="d-flex flex-column flex-lg-row justify-content-between align-items-start gap-3">
                <div>
                    <h3 class="fw-bold text-primary mb-1"><i class="ti tabler-mail me-2"></i>Email Center</h3>
                    <p class="text-muted mb-0">Browse Inbox and Sent Items live from Office 365. Compose still uses SMTP.</p>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <button type="button" class="btn btn-primary" wire:click="openComposeModal" @disabled(! $canSend) title="{{ $canSend ? 'Compose email' : 'No send permission' }}">
                        <i class="ti tabler-pencil me-1"></i>Compose
                    </button>
                    <button type="button" class="btn btn-outline-secondary" wire:click="refreshMailbox" wire:loading.attr="disabled" wire:target="refreshMailbox" @disabled(! $graphConfigured || $refreshing)>
                        <span wire:loading.remove wire:target="refreshMailbox"><i class="ti tabler-refresh me-1"></i>Refresh</span>
                        <span wire:loading wire:target="refreshMailbox"><i class="ti tabler-loader-2 me-1"></i>Refreshing...</span>
                    </button>
                    @if ($canManageMail ?? false)
                    <a href="{{ route('admin.settings.mail') }}" class="btn btn-outline-primary">
                        <i class="ti tabler-settings me-1"></i>Mail Settings
                    </a>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="mb-3">
        <ul class="nav nav-pills gap-2">
            <li class="nav-item">
                <button type="button" wire:click="setFilter('inbox')" class="nav-link {{ $filter === 'inbox' ? 'active' : '' }}">
                    <i class="ti tabler-inbox me-1"></i>Inbox
                    <span class="badge bg-label-{{ $filter === 'inbox' ? 'light' : 'info' }} ms-1">{{ $stats['inbox_count'] }}</span>
                </button>
            </li>
            <li class="nav-item">
                <button type="button" wire:click="setFilter('sent')" class="nav-link {{ $filter === 'sent' ? 'active' : '' }}">
                    <i class="ti tabler-send me-1"></i>Sent Items
                    <span class="badge bg-label-{{ $filter === 'sent' ? 'light' : 'primary' }} ms-1">{{ $stats['total_sent'] }}</span>
                </button>
            </li>
        </ul>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-body border-bottom">
            <div class="row g-2 align-items-center">
                <div class="col-md-8">
                    <div class="input-group">
                        <span class="input-group-text bg-white"><i class="ti tabler-search"></i></span>
                        <input type="search" wire:model.live.debounce.400ms="search" class="form-control" placeholder="Search subject, from, to..." autocomplete="off">
                    </div>
                </div>
                <div class="col-md-4 text-md-end">
                    <span class="text-muted small">{{ $total }} message{{ $total === 1 ? '' : 's' }}</span>
                </div>
            </div>
        </div>

        <div class="table-responsive" wire:loading.class="opacity-50" wire:target="setFilter,search,gotoPage,previousPage,nextPage,refreshMailbox">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width: 28%">From / To</th>
                        <th>Subject</th>
                        <th style="width: 140px">Date</th>
                        <th style="width: 120px">Case</th>
                        <th class="text-end" style="width: 160px">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($emails as $email)
                        @php
                            $encodedId = \App\Livewire\Admin\Email\EmailViewPage::encodeId($email->id);
                            $normalizedId = $email->normalizedMessageId();
                            $caseId = $normalizedId ? ($caseMap[$normalizedId] ?? null) : null;
                            $linkedCase = $caseId ? ($linkedCases[$caseId] ?? null) : null;
                            $viewUrl = route('admin.email.show', ['folder' => $filter === 'sent' ? 'sent' : 'inbox', 'messageId' => $encodedId]);
                        @endphp
                        <tr wire:key="graph-mail-{{ $email->id }}">
                            <td>
                                <div class="fw-semibold">{{ $email->direction === 'inbound' ? $email->fromName : $email->toAddress }}</div>
                                <small class="text-muted">{{ $email->direction === 'inbound' ? $email->fromAddress : $email->fromAddress }}</small>
                            </td>
                            <td>
                                <a href="{{ $viewUrl }}" class="fw-semibold text-decoration-none">
                                    {{ $email->subject }}
                                </a>
                                @if ($email->hasAttachments)
                                    <i class="ti tabler-paperclip text-muted ms-1" title="Has attachments"></i>
                                @endif
                            </td>
                            <td>
                                <small>{{ $email->date?->timezone(config('app.timezone'))->format('m/d/Y g:i A') ?? '—' }}</small>
                            </td>
                            <td>
                                @if ($linkedCase)
                                    <span class="badge bg-label-success">{{ $linkedCase->case_number }}</span>
                                @else
                                    <span class="text-muted small">—</span>
                                @endif
                            </td>
                            <td class="text-end text-nowrap">
                                <a href="{{ $viewUrl }}" class="btn btn-sm btn-outline-primary" title="View conversation">
                                    <i class="ti tabler-eye me-1"></i>View
                                </a>
                                @if (($canLink ?? false) && $email->internetMessageId)
                                    <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="openLinkModal({{ json_encode($email->internetMessageId) }})" title="Link to case">
                                        <i class="ti tabler-link"></i>
                                    </button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center py-5 text-muted">
                                @if (! $graphConfigured)
                                    Configure Microsoft Graph to load mailbox messages.
                                @elseif ($mailboxError)
                                    Unable to load messages.
                                @else
                                    No messages in this folder.
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($lastPage > 1)
            <div class="card-footer bg-white d-flex justify-content-between align-items-center">
                <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="previousPage" @disabled($page <= 1)>Previous</button>
                <span class="small text-muted">Page {{ $page }} of {{ $lastPage }}</span>
                <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="nextPage" @disabled($page >= $lastPage)>Next</button>
            </div>
        @endif
    </div>

    @include('livewire.admin.email.partials.compose-modal')
    @include('livewire.admin.email.partials.link-modal')
</div>
