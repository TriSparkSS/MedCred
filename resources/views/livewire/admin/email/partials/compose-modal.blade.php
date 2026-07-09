@if ($showComposeModal)
    <div class="modal fade show d-block" tabindex="-1" role="dialog" aria-modal="true" style="background: rgba(0,0,0,.5); z-index: 1090;" wire:key="email-compose-modal">
        <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
            <div class="modal-content shadow">
                <div class="modal-header border-bottom">
                    <h5 class="modal-title fw-semibold">
                        <i class="ti tabler-mail me-2"></i>{{ $composeInReplyTo ? 'Reply' : 'Compose Email' }}
                    </h5>
                    <button type="button" class="btn-close" wire:click="closeComposeModal" aria-label="Close"></button>
                </div>
                <form wire:submit.prevent="sendEmail" onsubmit="return false">
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
                                <select id="composeTemplateId" wire:model.live="composeTemplateId" class="form-select" @disabled(filled($composeInReplyTo))>
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
                        @if (! $smtpConfigured)
                            <div class="alert alert-warning mt-3 mb-0 small">
                                SMTP is not configured. <a href="{{ route('admin.settings.mail') }}">Configure mail settings</a> before sending.
                            </div>
                        @endif
                    </div>
                    <div class="modal-footer border-top">
                        <button type="button" class="btn btn-outline-secondary" wire:click="closeComposeModal">Cancel</button>
                        <button type="submit" class="btn btn-primary" @disabled(! $smtpConfigured || ! $canSend) wire:loading.attr="disabled" wire:target="sendEmail">
                            <span wire:loading.remove wire:target="sendEmail"><i class="ti tabler-send me-1"></i>Send Email</span>
                            <span wire:loading wire:target="sendEmail">Sending...</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endif
