<div class="container-fluid flex-grow-1 px-3 px-md-4 py-3 py-md-4">
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body d-flex flex-column flex-md-row justify-content-between align-items-start gap-3">
            <div>
                <h4 class="fw-bold text-primary mb-1"><i class="ti tabler-edit me-2"></i>Edit Application — {{ $case->case_number }}</h4>
                <p class="text-muted mb-0">Update case assignment, workflow, key dates, notes, and additional documents.</p>
            </div>
            <div class="d-flex gap-2">
                <a href="{{ route('admin.credentials.show', $case->id) }}" class="btn btn-outline-primary"><i class="ti tabler-eye me-1"></i>View Application</a>
                <a href="{{ route('admin.applications') }}" class="btn btn-outline-secondary"><i class="ti tabler-arrow-left me-1"></i>Back to Tracker</a>
            </div>
        </div>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-body p-4">
            <form wire:submit.prevent="save">
                <div class="mb-3 pb-3 border-bottom">
                    <h6 class="text-primary fw-semibold mb-1"><i class="ti tabler-link me-2"></i>Case Assignment</h6>
                </div>
                <div class="row g-4 mb-4">
                    <div class="col-md-4">
                        <label class="form-label fw-medium">Provider <span class="text-danger">*</span></label>
                        <select wire:model.live="formData.provider_id" class="form-select @error('formData.provider_id') is-invalid @enderror">
                            <option value="">Select provider...</option>
                            @foreach ($providers as $provider)
                                <option value="{{ $provider->id }}">{{ $provider->user->name ?? 'N/A' }} ({{ $provider->npi }})</option>
                            @endforeach
                        </select>
                        @error('formData.provider_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-medium">Practice <span class="text-danger">*</span></label>
                        <select wire:model.live="formData.practice_id" class="form-select @error('formData.practice_id') is-invalid @enderror">
                            <option value="">Select practice...</option>
                            @foreach ($practices as $practice)
                                <option value="{{ $practice->id }}">{{ $practice->legal_name }}</option>
                            @endforeach
                        </select>
                        @error('formData.practice_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-medium">Location</label>
                        <select wire:model="formData.location_id" class="form-select" @disabled(empty($practiceLocations))>
                            <option value="">Select location...</option>
                            @foreach ($practiceLocations as $loc)
                                <option value="{{ $loc['id'] }}">{{ $loc['name'] }}{{ $loc['is_primary'] ? ' (Primary)' : '' }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-medium">Payer <span class="text-danger">*</span></label>
                        <select wire:model="formData.payer_id" class="form-select @error('formData.payer_id') is-invalid @enderror">
                            <option value="">Select payer...</option>
                            @foreach ($payers as $payer)
                                <option value="{{ $payer->id }}">{{ $payer->name }}</option>
                            @endforeach
                        </select>
                        @error('formData.payer_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <label class="form-label fw-medium small mt-2 mb-1">Or enter payer name</label>
                        <input type="text" wire:model="payerName" class="form-control @error('payerName') is-invalid @enderror"
                            placeholder="Type a new or existing payer">
                        @error('payerName')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-medium">Application type <span class="text-danger">*</span></label>
                        <select wire:model="formData.enrollment_level" class="form-select @error('formData.enrollment_level') is-invalid @enderror">
                            @foreach (\App\Enums\EnrollmentLevel::cases() as $level)
                                <option value="{{ $level->value }}">{{ $level->label() }}</option>
                            @endforeach
                        </select>
                        @error('formData.enrollment_level')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-medium">Case Type</label>
                        <select wire:model="formData.case_type_id" class="form-select">
                            <option value="">Select type...</option>
                            @foreach ($caseTypes as $type)
                                <option value="{{ $type->id }}">{{ $type->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="mb-3 pb-3 border-bottom">
                    <h6 class="text-primary fw-semibold mb-1"><i class="ti tabler-settings me-2"></i>Workflow</h6>
                </div>
                <div class="row g-4 mb-4">
                    <div class="col-md-4">
                        <label class="form-label fw-medium">Status <span class="text-danger">*</span></label>
                        <select wire:model.live="formData.status_id" class="form-select @error('formData.status_id') is-invalid @enderror">
                            @foreach ($statuses as $status)
                                <option value="{{ $status->id }}">{{ $status->name }}</option>
                            @endforeach
                        </select>
                        @error('formData.status_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-medium">Delay Owner</label>
                        <select wire:model="formData.delay_owner_id" class="form-select">
                            <option value="">Auto from status</option>
                            @foreach ($delayOwners as $owner)
                                <option value="{{ $owner->id }}">{{ $owner->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-medium">Priority</label>
                        <select wire:model="formData.priority_id" class="form-select">
                            <option value="">Select priority...</option>
                            @foreach ($priorities as $priority)
                                <option value="{{ $priority->id }}">{{ $priority->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-medium">Assigned To</label>
                        <select wire:model="formData.assigned_admin_id" class="form-select">
                            <option value="">Unassigned</option>
                            @foreach ($admins as $admin)
                                <option value="{{ $admin->id }}">{{ $admin->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="mb-3 pb-3 border-bottom">
                    <h6 class="text-primary fw-semibold mb-1"><i class="ti tabler-calendar me-2"></i>Key Dates</h6>
                </div>
                <div class="row g-4 mb-4">
                    <div class="col-md-4">
                        <label class="form-label fw-medium">Intake Date</label>
                        <input type="date" wire:model="formData.intake_date" class="form-control">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-medium">Submission Date</label>
                        <input type="date" wire:model="formData.submission_date" class="form-control">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-medium">Next Follow-up</label>
                        <input type="date" wire:model="formData.next_follow_up_date" class="form-control">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-medium">Expected Completion</label>
                        <input type="date" wire:model="formData.expected_completion_date" class="form-control">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-medium">Effective Date</label>
                        <input type="date" wire:model="formData.effective_date" class="form-control">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-medium">Revalidation Due</label>
                        <input type="date" wire:model="formData.revalidation_due_date" class="form-control">
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-medium">Notes</label>
                        <textarea wire:model="formData.notes" rows="4" class="form-control" placeholder="Case notes..."></textarea>
                    </div>
                </div>

                {{-- Additional Documents --}}
                <div class="mb-3 pb-3 border-bottom">
                    <h6 class="text-primary fw-semibold mb-1"><i class="ti tabler-files me-2"></i>Upload Additional Documents</h6>
                    <p class="text-muted small mb-0">Upload supporting files, payer correspondence, or request letters. Selected files are also linked to the Practice and/or Provider profiles.</p>
                </div>
                <div class="row g-4 mb-4">
                    <div class="col-md-6">
                        <label class="form-label fw-medium">Attach Files</label>
                        <input type="file" wire:model="additionalDocuments" multiple
                            class="form-control @error('additionalDocuments.*') is-invalid @enderror"
                            accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                        @error('additionalDocuments.*')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        <div class="form-text">Accepted: PDF, DOC, DOCX, JPG, PNG (up to 10MB each).</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-medium">Document Type</label>
                        <select wire:model="additionalDocumentTypeId" class="form-select">
                            <option value="">Default (Payer Correspondence)</option>
                            @foreach ($documentTypes as $docType)
                                <option value="{{ $docType->id }}">{{ $docType->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12">
                        <div class="d-flex flex-wrap gap-4">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" wire:model="linkAdditionalDocsToPractice" id="editLinkPractice">
                                <label class="form-check-label" for="editLinkPractice">
                                    Also save to Practice Profile (License &amp; Documents)
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" wire:model="linkAdditionalDocsToProvider" id="editLinkProvider">
                                <label class="form-check-label" for="editLinkProvider">
                                    Also save to Provider Profile (Provider Documents)
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="d-flex justify-content-end gap-2 pt-3 border-top">
                    <a href="{{ route('admin.credentials.show', $case->id) }}" class="btn btn-outline-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary"><i class="ti tabler-device-floppy me-1"></i>Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <div class="mt-4">
        <livewire:admin.documents.entity-documents-section
            :case-id="$case->id"
            :practice-id="$case->practice_id"
            :provider-id="$case->provider_id"
            heading="Additional Application Documents"
            :show-profile-link="false"
            :key="'edit-case-docs-'.$case->id" />
    </div>
</div>
