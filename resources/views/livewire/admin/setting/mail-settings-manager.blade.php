<div class="container-fluid px-3 px-md-4 py-4">
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body">
            <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
                <div>
                    <h4 class="mb-1 fw-bold text-primary">Mail / SMTP Settings</h4>
                    <small class="text-muted">Configure Office 365 SMTP for sending. Email Center reads the mailbox live via Microsoft Graph.</small>
                </div>
                <a href="{{ route('admin.settings') }}" class="btn btn-outline-secondary btn-sm">
                    <i class="ti tabler-arrow-left me-1"></i> Back to Settings
                </a>
            </div>
        </div>
    </div>

    <form wire:submit.prevent="save">
        <div class="row g-4">
            <div class="col-lg-8">
                <div class="card shadow-sm border-0">
                    <div class="card-header bg-white">
                        <h6 class="mb-0 fw-semibold">SMTP Server</h6>
                    </div>
                    <div class="card-body">
                        <div class="form-check form-switch mb-4">
                            <input type="checkbox" class="form-check-input" id="mail_enabled" wire:model="enabled">
                            <label class="form-check-label" for="mail_enabled">Enable outbound email via SMTP</label>
                        </div>

                        <div class="row g-3">
                            <div class="col-md-8">
                                <label class="form-label">SMTP Host <span class="text-danger">*</span></label>
                                <input type="text" wire:model="host" class="form-control @error('host') is-invalid @enderror" placeholder="smtp.office365.com">
                                @error('host')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Port <span class="text-danger">*</span></label>
                                <input type="number" wire:model="port" class="form-control @error('port') is-invalid @enderror">
                                @error('port')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Encryption</label>
                                <select wire:model="encryption" class="form-select">
                                    <option value="tls">STARTTLS (TLS) — port 587</option>
                                    <option value="ssl">SSL — port 465</option>
                                    <option value="none">None</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Scheme</label>
                                <select wire:model="scheme" class="form-select">
                                    <option value="smtp">smtp (STARTTLS)</option>
                                    <option value="smtps">smtps (SSL)</option>
                                </select>
                                <small class="text-muted">Office 365: use <strong>smtp</strong> on port 587.</small>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Username <span class="text-danger">*</span></label>
                                <input type="email" wire:model="username" class="form-control @error('username') is-invalid @enderror" placeholder="credentialing@revantagehbs.com">
                                @error('username')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">
                                    Password @if(!$hasPassword)<span class="text-danger">*</span>@endif
                                </label>
                                <input type="password" wire:model="password" class="form-control @error('password') is-invalid @enderror" autocomplete="new-password" placeholder="{{ $hasPassword ? 'Leave blank to keep current password' : 'Enter SMTP password' }}">
                                @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                @if ($hasPassword)
                                    <small class="text-success"><i class="ti tabler-check me-1"></i>Password is saved (encrypted).</small>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card shadow-sm border-0 mt-4">
                    <div class="card-header bg-white">
                        <h6 class="mb-0 fw-semibold">Sender Identity</h6>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">From Email <span class="text-danger">*</span></label>
                                <input type="email" wire:model="from_address" class="form-control @error('from_address') is-invalid @enderror">
                                @error('from_address')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">From Name <span class="text-danger">*</span></label>
                                <input type="text" wire:model="from_name" class="form-control @error('from_name') is-invalid @enderror">
                                @error('from_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card shadow-sm border-0 mt-4">
                    <div class="card-header bg-white">
                        <h6 class="mb-0 fw-semibold">Mailbox (Microsoft Graph)</h6>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-info small mb-0">
                            IMAP sync has been removed. Email Center loads Inbox and Sent Items <strong>live</strong> from Microsoft Graph
                            (message bodies are not stored in the CRM). Configure Graph in the panel on the right. SMTP above is still used for sending.
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-header bg-white">
                        <h6 class="mb-0 fw-semibold">Actions</h6>
                    </div>
                    <div class="card-body d-grid gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="ti tabler-device-floppy me-1"></i> Save Settings
                        </button>
                    </div>
                </div>

                <div class="card shadow-sm border-0">
                    <div class="card-header bg-white">
                        <h6 class="mb-0 fw-semibold">Send Test Email</h6>
                    </div>
                    <div class="card-body">
                        <p class="text-muted small">Verify SMTP credentials before sending credentialing emails.</p>
                        <div class="mb-3">
                            <label class="form-label">Send test to</label>
                            <input type="email" wire:model="testEmailTo" class="form-control @error('testEmailTo') is-invalid @enderror" placeholder="you@example.com">
                            @error('testEmailTo')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <button type="button" wire:click="sendTest" class="btn btn-outline-primary w-100">
                            <i class="ti tabler-send me-1"></i> Send Test
                        </button>
                        <small class="text-muted d-block mt-2">Sent via SMTP. Use Email Center → Refresh to see it in Sent Items.</small>
                    </div>
                </div>

                <div class="card shadow-sm border-0 mt-4">
                    <div class="card-header bg-white">
                        <h6 class="mb-0 fw-semibold">Microsoft Graph OAuth 2.0</h6>
                    </div>
                    <div class="card-body">
                        <p class="text-muted small mb-3">
                            Required for Email Center. Uses client-credentials OAuth — configure in <code>.env</code>.
                        </p>
                        @if ($graphConfigured)
                            <div class="alert alert-success small mb-3">
                                <i class="ti tabler-check me-1"></i>
                                Graph configured for <strong>{{ $graphMailbox }}</strong>.
                            </div>
                        @else
                            <div class="alert alert-warning small mb-3">
                                <i class="ti tabler-alert-triangle me-1"></i>
                                Graph not configured.
                                @if (! empty($graphMissingKeys))
                                    Missing: <code>{{ implode(', ', $graphMissingKeys) }}</code>
                                @endif
                            </div>
                        @endif
                        <ol class="small text-muted ps-3 mb-3">
                            <li>Register an Entra ID app and create a client secret.</li>
                            <li>Add Application permission <code>Mail.Read</code> and grant admin consent.</li>
                            <li>Set env vars carefully:
                                <ul class="mt-1 mb-0">
                                    <li><code>GRAPH_TENANT_ID</code> = Directory (tenant) ID (GUID)</li>
                                    <li><code>GRAPH_CLIENT_ID</code> = Application (client) ID (GUID) — <strong>not</strong> the secret</li>
                                    <li><code>GRAPH_CLIENT_SECRET</code> = Value from Certificates &amp; secrets</li>
                                    <li><code>GRAPH_MAILBOX</code> = mailbox email to read</li>
                                </ul>
                            </li>
                            <li>Run <code>php artisan config:clear</code> then Test Graph below.</li>
                        </ol>
                        <button type="button" wire:click="testGraph" class="btn btn-outline-success w-100" @disabled(! $graphConfigured)>
                            <i class="ti tabler-brand-onedrive me-1"></i> Test Graph Connection
                        </button>
                    </div>
                </div>

                <div class="alert alert-info mt-4 small mb-0">
                    <strong>Office 365 reference:</strong><br>
                    SMTP: smtp.office365.com · Port 587 · STARTTLS<br>
                    Mailbox: Microsoft Graph live Inbox + Sent Items
                </div>
            </div>
        </div>
    </form>
</div>
