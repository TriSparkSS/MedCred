<div class="container-fluid flex-grow-1 px-3 px-md-4 py-3 py-md-4">

    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body d-flex flex-column flex-md-row justify-content-between align-items-start gap-3">
            <div>
                <h3 class="fw-bold text-primary mb-1"><i class="ti tabler-mail me-2"></i>Email Center</h3>
                <p class="text-muted mb-0">Operational inbox for credentialing correspondence and SLA reminders.</p>
            </div>
            <button wire:click="openComposeModal" class="btn btn-primary">
                <i class="ti tabler-send me-1"></i>Compose Email
            </button>
            <button wire:click="syncMailbox" class="btn btn-outline-secondary">
                <i class="ti tabler-refresh me-1"></i>Sync Mailbox
            </button>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-xl-3">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body">
                    <p class="text-uppercase text-muted small mb-1">Emails Sent</p>
                    <h3 class="fw-bold mb-0">{{ $stats['total_sent'] }}</h3>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body">
                    <p class="text-uppercase text-muted small mb-1">Unlinked Inbound</p>
                    <h3 class="fw-bold mb-0 text-warning">{{ $stats['unlinked_inbound'] }}</h3>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body">
                    <p class="text-uppercase text-muted small mb-1">Auto Reminders</p>
                    <h3 class="fw-bold mb-0">{{ $stats['reminders_sent'] }}</h3>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card shadow-sm border-0 h-100">
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
                            </td>
                            <td class="small text-muted">
                                {{ ($email->sent_at ?? $email->received_at ?? $email->created_at)?->format('m/d/Y g:i A') }}
                            </td>
                            <td class="text-end">
                                <div class="btn-group btn-group-sm">
                                    @if (! $email->credentialing_case_id)
                                        <button wire:click="openLinkModal({{ $email->id }})" class="btn btn-outline-primary" title="Link to case">
                                            <i class="ti tabler-link"></i>
                                        </button>
                                    @endif
                                    @foreach ($email->attachments as $attachment)
                                        <button wire:click="importAttachment({{ $attachment->id }})"
                                            class="btn btn-outline-secondary" title="Import attachment">
                                            <i class="ti tabler-file-import"></i>
                                        </button>
                                    @endforeach
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted py-5">No emails yet. Compose a message or run SLA checks to send auto-reminders.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer bg-white">{{ $emails->links() }}</div>
    </div>

    @if ($showLinkModal)
        <div class="modal fade show d-block" tabindex="-1" style="background: rgba(0,0,0,.5);">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Link Email to Case</h5>
                        <button type="button" class="btn-close" wire:click="$set('showLinkModal', false)"></button>
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
                            @error('linkCaseId')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" wire:click="$set('showLinkModal', false)">Cancel</button>
                            <button type="submit" class="btn btn-primary">Link Email</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif

    @if ($showComposeModal)
        <div class="modal fade show d-block" tabindex="-1" style="background: rgba(0,0,0,.5);">
            <div class="modal-dialog modal-dialog-centered modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Compose Email</h5>
                        <button type="button" class="btn-close" wire:click="$set('showComposeModal', false)"></button>
                    </div>
                    <form wire:submit.prevent="sendEmail">
                        <div class="modal-body">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Case (optional)</label>
                                    <select wire:model.live="composeCaseId" class="form-select">
                                        <option value="">No case</option>
                                        @foreach ($cases as $case)
                                            <option value="{{ $case->id }}">{{ $case->case_number }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Template</label>
                                    <select wire:model.live="composeTemplateId" class="form-select">
                                        <option value="">Custom message</option>
                                        @foreach ($templates as $template)
                                            <option value="{{ $template->id }}">{{ $template->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-12">
                                    <label class="form-label">To <span class="text-danger">*</span></label>
                                    <input type="email" wire:model="composeTo" class="form-control @error('composeTo') is-invalid @enderror">
                                    @error('composeTo')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Subject <span class="text-danger">*</span></label>
                                    <input type="text" wire:model="composeSubject" class="form-control @error('composeSubject') is-invalid @enderror">
                                    @error('composeSubject')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Body <span class="text-danger">*</span></label>
                                    <textarea wire:model="composeBody" rows="8" class="form-control @error('composeBody') is-invalid @enderror"></textarea>
                                    @error('composeBody')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" wire:click="$set('showComposeModal', false)">Cancel</button>
                            <button type="submit" class="btn btn-primary">Send Email</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
</div>
