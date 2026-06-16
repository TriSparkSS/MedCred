<div class="container-fluid px-3 px-md-4 py-4">
    <div class="card shadow-sm border-0">
        <div class="card-body">
            <div class="d-flex align-items-start justify-content-between mb-4">
                <div>
                    <h4 class="mb-1 fw-bold text-primary">Edit Practice</h4>
                    <p class="text-muted mb-0">Update practice, linked user, and primary address details.</p>
                </div>
                <a href="{{ route('admin.practices') }}" class="btn btn-outline-secondary">Back to list</a>
            </div>

            @php use App\Enums\PracticeStatus; @endphp
            <form wire:submit.prevent="save">
                <h6 class="text-uppercase text-muted mb-3">Practice Details</h6>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Legal Name</label>
                        <input type="text" wire:model="formData.legal_name" class="form-control">
                        @error('formData.legal_name') <span class="text-danger">{{ $message }}</span> @enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">DBA Name</label>
                        <input type="text" wire:model="formData.dba_name" class="form-control">
                        @error('formData.dba_name') <span class="text-danger">{{ $message }}</span> @enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">EIN/TIN</label>
                        <input type="text" wire:model="formData.ein_tin" class="form-control">
                        @error('formData.ein_tin') <span class="text-danger">{{ $message }}</span> @enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Group NPI</label>
                        <input type="text" wire:model="formData.group_npi" class="form-control">
                        @error('formData.group_npi') <span class="text-danger">{{ $message }}</span> @enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Taxonomy Code</label>
                        <input type="text" wire:model="formData.taxonomy_code" class="form-control">
                        @error('formData.taxonomy_code') <span class="text-danger">{{ $message }}</span> @enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Email</label>
                        <input type="email" wire:model="formData.email" class="form-control">
                        @error('formData.email') <span class="text-danger">{{ $message }}</span> @enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Phone</label>
                        <input type="text" wire:model="formData.phone" class="form-control">
                        @error('formData.phone') <span class="text-danger">{{ $message }}</span> @enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Fax</label>
                        <input type="text" wire:model="formData.fax" class="form-control">
                        @error('formData.fax') <span class="text-danger">{{ $message }}</span> @enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Website</label>
                        <input type="url" wire:model="formData.website" class="form-control">
                        @error('formData.website') <span class="text-danger">{{ $message }}</span> @enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Status</label>
                        <select wire:model="formData.status" class="form-select">
                            <option value="{{ PracticeStatus::PENDING->value }}">Pending</option>
                            <option value="{{ PracticeStatus::ACTIVE->value }}">Active</option>
                            <option value="{{ PracticeStatus::INACTIVE->value }}">Inactive</option>
                        </select>
                        @error('formData.status') <span class="text-danger">{{ $message }}</span> @enderror
                    </div>
                </div>

                <h6 class="text-uppercase text-muted mt-4 mb-3">Primary Address</h6>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Location Name</label>
                        <input type="text" wire:model="addressData.location_name" class="form-control">
                        @error('addressData.location_name') <span class="text-danger">{{ $message }}</span> @enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Address Status</label>
                        <select wire:model="addressData.status" class="form-select">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                        @error('addressData.status') <span class="text-danger">{{ $message }}</span> @enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Address 1</label>
                        <input type="text" wire:model="addressData.address1" class="form-control">
                        @error('addressData.address1') <span class="text-danger">{{ $message }}</span> @enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Address 2</label>
                        <input type="text" wire:model="addressData.address2" class="form-control">
                        @error('addressData.address2') <span class="text-danger">{{ $message }}</span> @enderror
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">City</label>
                        <input type="text" wire:model="addressData.city" class="form-control">
                        @error('addressData.city') <span class="text-danger">{{ $message }}</span> @enderror
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">State</label>
                        <input type="text" wire:model="addressData.state" class="form-control">
                        @error('addressData.state') <span class="text-danger">{{ $message }}</span> @enderror
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Zip Code</label>
                        <input type="text" wire:model="addressData.zip_code" class="form-control">
                        @error('addressData.zip_code') <span class="text-danger">{{ $message }}</span> @enderror
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Country</label>
                        <input type="text" wire:model="addressData.country" class="form-control">
                        @error('addressData.country') <span class="text-danger">{{ $message }}</span> @enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Address Phone</label>
                        <input type="text" wire:model="addressData.phone" class="form-control">
                        @error('addressData.phone') <span class="text-danger">{{ $message }}</span> @enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Address Fax</label>
                        <input type="text" wire:model="addressData.fax" class="form-control">
                        @error('addressData.fax') <span class="text-danger">{{ $message }}</span> @enderror
                    </div>
                </div>

                <div class="mt-4">
                    <button class="btn btn-primary">Save</button>
                    <a href="{{ route('admin.practices') }}" class="btn btn-outline-secondary ms-2">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>
