@if ($showLinkModal)
    <div class="modal fade show d-block" tabindex="-1" role="dialog" aria-modal="true" style="background: rgba(0,0,0,.5); z-index: 1090;" wire:key="email-link-modal">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content shadow">
                <div class="modal-header border-bottom">
                    <h5 class="modal-title fw-semibold"><i class="ti tabler-link me-2"></i>Link Email to Case</h5>
                    <button type="button" class="btn-close" wire:click="closeLinkModal" aria-label="Close"></button>
                </div>
                <form wire:submit.prevent="linkEmail" onsubmit="return false">
                    <div class="modal-body">
                        <label class="form-label" for="linkCaseId">Credentialing Case</label>
                        <select id="linkCaseId" wire:model="linkCaseId" class="form-select @error('linkCaseId') is-invalid @enderror">
                            <option value="">Select case...</option>
                            @foreach ($cases as $case)
                                <option value="{{ $case->id }}">{{ $case->case_number }} — {{ $case->provider->user->name ?? '' }}</option>
                            @endforeach
                        </select>
                        @error('linkCaseId')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>
                    <div class="modal-footer border-top">
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
