<div class="container-xxl">
    <div class="authentication-wrapper authentication-basic container-p-y">
        <div class="authentication-inner py-6">
            <div class="card">
                <div class="card-body">
                    <div class="app-brand justify-content-center mb-6">
                        <a href="{{ $guard === 'web' ? route('portal.login') : route('login') }}" class="app-brand-link">
                            <span class="app-brand-logo demo">
                                <img src="{{ asset('assets/logo/logo.jpeg') }}" alt="MedCred" style="height:140px;width:auto;max-width:100%;object-fit:contain;">
                            </span>
                        </a>
                    </div>

                    <h5 class="mb-2 fw-bold">Reset Password</h5>
                    <p class="mb-4 text-muted small">
                        Enter your new password below to complete your password reset.
                    </p>

                    <form class="mb-4" wire:submit.prevent="resetPassword">
                        <div class="mb-4">
                            <label for="email" class="form-label">Email Address</label>
                            <input type="email" id="email" wire:model="email"
                                class="form-control @error('email') is-invalid @enderror"
                                placeholder="Enter your email" />
                            @error('email')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-4">
                            <label for="password" class="form-label">New Password</label>
                            <input type="password" id="password" wire:model="password"
                                class="form-control @error('password') is-invalid @enderror"
                                placeholder="*********" />
                            @error('password')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-4">
                            <label for="password_confirmation" class="form-label">Confirm Password</label>
                            <input type="password" id="password_confirmation" wire:model="password_confirmation"
                                class="form-control" placeholder="*********" />
                        </div>

                        <button type="submit" class="btn btn-primary d-grid w-100" wire:loading.attr="disabled">
                            <span wire:loading.remove>Set New Password</span>
                            <span wire:loading>Resetting...</span>
                        </button>
                    </form>

                    <div class="text-center">
                        <a href="{{ $guard === 'web' ? route('portal.login') : route('login') }}" class="d-flex align-items-center justify-content-center small">
                            <i class="ti tabler-chevron-left me-1"></i> Back to login
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
