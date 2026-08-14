<div class="container-xxl">
    <div class="authentication-wrapper authentication-basic container-p-y">
        <div class="authentication-inner py-6">
            <div class="card">
                <div class="card-body">
                    <div class="app-brand justify-content-center mb-6">
                        <a href="{{ route('login') }}" class="app-brand-link">
                            <span class="app-brand-logo demo">
                                <img src="{{ asset('assets/logo/login-logo.jpeg') }}" height="150" width="250"
                                    alt="" srcset="">
                            </span>
                        </a>
                    </div>

                    {{-- <h4 class="mb-1">Admin Login</h4> --}}
                    <p class="mb-6">Sign in to your admin panel</p>

                    <form class="mb-4" wire:submit.prevent="authenticate">
                        <div class="mb-6 form-control-validation">
                            <label for="login" class="form-label">Email or Username</label>
                            <input type="text" class="form-control @error('login') is-invalid @enderror"
                                wire:model.defer="login" placeholder="Enter your email or username" autofocus />
                            @error('login')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-6 form-password-toggle form-control-validation">
                            <label class="form-label" for="password"> Password</label>
                            <div class="input-group input-group-merge">
                                <input type="password" class="form-control @error('password') is-invalid @enderror"
                                    wire:model.defer="password" placeholder="*********" aria-describedby="password" />
                                <span class="input-group-text cursor-pointer">
                                    <i class="icon-base ti tabler-eye-off"></i>
                                </span>
                                @error('password')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="my-8">
                            <div class="form-check mb-0 ms-2">
                                <input class="form-check-input" type="checkbox" id="remember-me"
                                    wire:model="remember" />
                                <label class="form-check-label" for="remember-me"> Remember Me</label>
                            </div>
                        </div>

                        <div class="mb-6">
                            <button class="btn btn-primary d-grid w-100" type="submit" wire:loading.attr="disabled">
                                <span wire:loading.remove>Login</span>
                                <span wire:loading>Please wait...</span>
                            </button>
                        </div>
                    </form>
                    <div class="mt-5">
                        <div class="p-3 rounded-2" style="border: 1px solid #f5c2c7; background-color: #fff5f5;">
                            
                            <p class="mb-2 fw-bold" style="color: #b91c1c; font-size: 14px;">
                                ⚠️ IMPORTANT NOTICE
                            </p>

                            <p class="mb-0" style="font-size: 13px; color: #374151; line-height: 1.5;">
                                This system is operated by <strong>Revantage Healthcare Business Solutions LLC</strong> and may contain
                                <strong>Protected Health Information (PHI)</strong>. Access is strictly limited to authorized users.
                                Unauthorized access, use, or disclosure is prohibited and may result in disciplinary action and legal consequences.
                                All activities are monitored and audited in compliance with <strong>HIPAA</strong> and applicable security regulations.
                            </p>

                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
