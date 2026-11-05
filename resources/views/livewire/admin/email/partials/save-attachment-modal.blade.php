@if ($showSaveAttachmentModal)
    <div class="modal fade show d-block" tabindex="-1" role="dialog" aria-modal="true" style="background: rgba(0,0,0,.5); z-index: 1090;" wire:key="email-save-attachment-modal">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content shadow">
                <div class="modal-header border-bottom">
                    <h5 class="modal-title fw-semibold"><i class="ti tabler-file-import me-2"></i>Save Attachment to Documents</h5>
                    <button type="button" class="btn-close" wire:click="closeSaveAttachmentModal" aria-label="Close"></button>
                </div>
                <form wire:submit.prevent="saveAttachmentToDocuments" onsubmit="return false">
                    <div class="modal-body">
                        @if ($ingestAttachmentName)
                            <div class="alert alert-light border d-flex align-items-center gap-2 py-2 px-3 mb-3">
                                <i class="ti tabler-paperclip text-primary"></i>
                                <span class="small fw-semibold text-truncate">{{ $ingestAttachmentName }}</span>
                            </div>
                        @endif

                        <div class="mb-3">
                            <label class="form-label" for="ingestTitle">Document Title <span class="text-danger">*</span></label>
                            <input id="ingestTitle" type="text" wire:model="ingestTitle" class="form-control @error('ingestTitle') is-invalid @enderror">
                            @error('ingestTitle')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label" for="ingestDocumentTypeId">Document Type <span class="text-danger">*</span></label>
                            <select id="ingestDocumentTypeId" wire:model="ingestDocumentTypeId" class="form-select @error('ingestDocumentTypeId') is-invalid @enderror">
                                <option value="">Select document type...</option>
                                @foreach ($documentTypes ?? [] as $docType)
                                    <option value="{{ $docType->id }}">{{ $docType->name }}</option>
                                @endforeach
                            </select>
                            @error('ingestDocumentTypeId')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label" for="ingestCaseId">Credentialing Case</label>
                            <select id="ingestCaseId" wire:model.live="ingestCaseId" class="form-select @error('ingestCaseId') is-invalid @enderror">
                                <option value="">Select case (optional)...</option>
                                @foreach ($cases as $case)
                                    <option value="{{ $case->id }}">{{ $case->case_number }} — {{ $case->provider->user->name ?? '' }}</option>
                                @endforeach
                            </select>
                            @error('ingestCaseId')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label" for="ingestProviderId">Provider</label>
                            <select id="ingestProviderId" wire:model="ingestProviderId" class="form-select @error('ingestProviderId') is-invalid @enderror">
                                <option value="">Select provider (optional)...</option>
                                @foreach ($providers ?? [] as $provider)
                                    <option value="{{ $provider->id }}">{{ $provider->user->name ?? 'Provider #'.$provider->id }}{{ $provider->npi ? ' (NPI: '.$provider->npi.')' : '' }}</option>
                                @endforeach
                            </select>
                            @error('ingestProviderId')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label" for="ingestExpiryDate">Expiry Date</label>
                            <input id="ingestExpiryDate" type="date" wire:model="ingestExpiryDate" class="form-control @error('ingestExpiryDate') is-invalid @enderror">
                            @error('ingestExpiryDate')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>

                        <div class="mb-0">
                            <label class="form-label" for="ingestNotes">Notes</label>
                            <textarea id="ingestNotes" rows="2" wire:model="ingestNotes" class="form-control @error('ingestNotes') is-invalid @enderror" placeholder="Optional classification or verification notes..."></textarea>
                            @error('ingestNotes')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>
                    </div>
                    <div class="modal-footer border-top">
                        <button type="button" class="btn btn-outline-secondary" wire:click="closeSaveAttachmentModal">Cancel</button>
                        <button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:target="saveAttachmentToDocuments">
                            <span wire:loading.remove wire:target="saveAttachmentToDocuments">Save to Documents</span>
                            <span wire:loading wire:target="saveAttachmentToDocuments">Saving...</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endif
