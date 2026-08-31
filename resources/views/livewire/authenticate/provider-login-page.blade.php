<div class="container-xxl">
    <div class="authentication-wrapper authentication-basic container-p-y">
        <div class="authentication-inner py-6">
            <div class="card">
                <div class="card-body">
                    <div class="app-brand justify-content-center mb-6">
                        <a href="{{ route('provider.login') }}" class="app-brand-link">
                            <span class="app-brand-logo demo">
                                <img src="{{ asset('assets/logo/login-logo.jpeg') }}" height="150" width="250"
                                    alt="">
                            </span>
                        </a>
                    </div>
                    <p class="mb-6">Sign in to your provider credentialing portal</p>
                    <form wire:submit.prevent="authenticate">
                        <div class="mb-4">
                            <label class="form-label">Email</label>
                            <input type="email" wire:model="email"
                                class="form-control @error('email') is-invalid @enderror">
                            @error('email')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="mb-4">
                            <label class="form-label">Password</label>
                            <input type="password" wire:model="password"
                                class="form-control @error('password') is-invalid @enderror">
                            @error('password')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="mb-4 form-check">
                            <input type="checkbox" wire:model="remember" class="form-check-input" id="remember">
                            <label class="form-check-label" for="remember">Remember me</label>
                        </div>
                        <button type="submit" class="btn btn-primary w-100" wire:loading.attr="disabled">Sign
                            In</button>
                    </form>
                    <p class="text-center text-muted small mt-4 mb-0">
                        Staff admin? <a href="{{ route('login') }}">Admin login</a>
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>
