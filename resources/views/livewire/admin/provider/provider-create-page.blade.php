<div class="container-fluid px-3 px-md-4 py-4">
    <div class="card shadow-sm border-0">
        <div class="card-body">
            <div class="d-flex align-items-start justify-content-between mb-4">
                <div>
                    <h4 class="mb-1 fw-bold text-primary">Add Provider</h4>
                    <p class="text-muted mb-0">Create a new provider record.</p>
                </div>
                <div>
                    <a href="{{ route('admin.providers') }}" class="btn btn-outline-secondary">Back to list</a>
                </div>
            </div>

            <form wire:submit.prevent="save">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Name</label>
                        <input type="text" wire:model="userData.name" class="form-control">
                        @error('userData.name')
                            <span class="text-danger">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Email</label>
                        <input type="email" wire:model="userData.email" class="form-control">
                        @error('userData.email')
                            <span class="text-danger">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Phone</label>
                        <input type="text" wire:model="userData.phone" class="form-control">
                        @error('userData.phone')
                            <span class="text-danger">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Password</label>
                        <input type="password" wire:model="userData.password" class="form-control">
                        @error('userData.password')
                            <span class="text-danger">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Specialty</label>
                        <select wire:model="formData.specialty_id" class="form-select">
                            <option value="">Select specialty...</option>
                            @foreach ($specialties as $specialty)
                                <option value="{{ $specialty->id }}">{{ $specialty->name }}</option>
                            @endforeach
                        </select>
                        @error('formData.specialty_id')
                            <span class="text-danger">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">NPI</label>
                        <input type="text" wire:model="formData.npi" class="form-control">
                        @error('formData.npi')
                            <span class="text-danger">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Status</label>
                        <select wire:model="formData.status" class="form-select">
                            <option value="">Select status</option>
                            <option value="pending">Pending</option>
                            <option value="approved">Approved</option>
                            <option value="rejected">Rejected</option>
                        </select>
                        @error('formData.status')
                            <span class="text-danger">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="col-12">
                        <label class="form-label">Practice</label>
                        <input type="text" wire:model="formData.practice" class="form-control">
                        @error('formData.practice')
                            <span class="text-danger">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="col-12">
                        <label class="form-label">Address</label>
                        <input type="text" wire:model="formData.address" class="form-control">
                        @error('formData.address')
                            <span class="text-danger">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">City</label>
                        <input type="text" wire:model="formData.city" class="form-control">
                        @error('formData.city')
                            <span class="text-danger">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">State</label>
                        <input type="text" wire:model="formData.state" class="form-control" >
                        @error('formData.state')
                            <span class="text-danger">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Zip</label>
                        <input type="text" wire:model="formData.zip" class="form-control">
                        @error('formData.zip')
                            <span class="text-danger">{{ $message }}</span>
                        @enderror
                    </div>
                </div>

                <div class="mt-4">
                    <button class="btn btn-primary">Create Provider</button>
                    <a href="{{ route('admin.providers') }}" class="btn btn-outline-secondary ms-2">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>
