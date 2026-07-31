@if ($showThreadDrawer)
    <div class="offcanvas-backdrop fade show" wire:click="closeThread" style="z-index: 1085;"></div>
    <div class="offcanvas offcanvas-end show" tabindex="-1" style="visibility: visible; z-index: 1090; width: min(480px, 100vw);" wire:key="email-thread-drawer">
        <div class="offcanvas-header border-bottom">
            <h5 class="offcanvas-title fw-semibold"><i class="ti tabler-messages me-2"></i>Email Thread</h5>
            <button type="button" class="btn-close" wire:click="closeThread" aria-label="Close"></button>
        </div>
        <div class="offcanvas-body">
            @forelse ($threadMessages as $msg)
                <div class="border rounded p-3 mb-3 {{ $msg->direction === 'outbound' ? 'border-primary-subtle bg-primary-subtle' : 'border-info-subtle bg-info-subtle' }}" wire:key="thread-msg-{{ $msg->id }}">
                    <div class="d-flex justify-content-between small text-muted mb-2">
                        <span>{{ $msg->direction === 'outbound' ? 'To: ' . $msg->to_address : 'From: ' . $msg->from_address }}</span>
                        <span>{{ ($msg->sent_at ?? $msg->received_at ?? $msg->created_at)?->format('m/d/Y g:i A') }}</span>
                    </div>
                    <h6 class="fw-semibold mb-2">{{ $msg->subject }}</h6>
                    <div class="small text-body" style="white-space: pre-wrap;">{{ $msg->displayBody() }}</div>
                    @if ($msg->attachments->isNotEmpty())
                        <div class="mt-2 d-flex flex-wrap gap-1">
                            @foreach ($msg->attachments as $attachment)
                                <span class="badge bg-secondary">{{ $attachment->original_name }}</span>
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
                <p class="text-muted mb-0">No messages in this thread.</p>
            @endforelse
        </div>
    </div>
@endif
