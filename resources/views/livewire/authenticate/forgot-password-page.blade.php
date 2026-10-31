<div class="container-xxl">
    <div class="authentication-wrapper authentication-basic container-p-y">
        <div class="authentication-inner py-6">
            <div class="card">
                <div class="card-body">
                    <div class="app-brand justify-content-center mb-6">
                        <a href="{{ $guard === 'web' ? route('portal.login') : route('login') }}" class="app-brand-link">
                            <span class="app-brand-logo demo">
                                <img src="{{ asset('assets/logo/login-logo.jpeg') }}" height="150" width="250" alt="">
                            </span>
                        </a>
                    </div>

                    <h5 class="mb-2 fw-bold">Forgot Password?</h5>
                    <p class="mb-4 text-muted small">
                        Enter your email address and we will send you a link to reset your password.
                    </p>

                    @if ($statusSent && $statusMessage)
                        <div class="alert alert-success small mb-4" role="alert">
                            {{ $statusMessage }}
                        </div>
                    @endif

                    <form class="mb-4" wire:submit.prevent="sendResetLink">
                        <div class="mb-4">
                            <label for="email" class="form-label">Email Address</label>
                            <input type="email" id="email" wire:model="email"
                                class="form-control @error('email') is-invalid @enderror"
                                placeholder="Enter your email" autofocus />
                            @error('email')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <button type="submit" class="btn btn-primary d-grid w-100" wire:loading.attr="disabled">
                            <span wire:loading.remove>Send Reset Link</span>
                            <span wire:loading>Sending...</span>
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
