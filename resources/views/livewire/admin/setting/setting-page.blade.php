<div class="container-fluid flex-grow-1 px-3 px-md-4 py-3 py-md-4">
    <div class="row g-4 mb-5">
        <div class="col-12">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-start gap-3">
                <div>
                    <h3 class="fw-bold text-primary mb-2">Admin & Settings Dashboard</h3>
                    <p class="text-muted mb-0">Configure platform-wide rules, manage user access, and monitor system
                        health.</p>
                </div>
                <div class="d-flex flex-column flex-sm-row align-items-sm-center gap-2">
                    <span class="small text-uppercase text-muted">Last Sync Status</span>
                    <span class="badge bg-success rounded-pill">All Systems Nominal</span>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-xl-8">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body">
                    <div class="d-flex align-items-start justify-content-between mb-4">
                        <div>
                            <h5 class="fw-bold mb-1">System Health & Active Integrations</h5>
                            <p class="text-muted mb-0">Monitor critical platform metrics and integration status in one
                                place.</p>
                        </div>
                        <a href="#" class="btn btn-sm btn-outline-secondary">View Detailed Logs</a>
                    </div>

                    <div class="row g-3 mb-4">
                        <div class="col-sm-4">
                            <div class="card h-100 border-0 shadow-sm">
                                <div class="card-body">
                                    <p class="text-uppercase small text-muted mb-2">OCR Engine Uptime</p>
                                    <h3 class="fw-bold mb-1">99.9%</h3>
                                    <span class="text-success small"><i class="ti tabler-trending-up"></i> 0.2%</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-sm-4">
                            <div class="card h-100 border-0 shadow-sm">
                                <div class="card-body">
                                    <p class="text-uppercase small text-muted mb-2">Payer Portal Connects</p>
                                    <div class="d-flex align-items-center justify-content-between">
                                        <h4 class="fw-bold mb-0">12/14</h4>
                                        <span class="badge bg-warning text-dark">Pending</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-sm-4">
                            <div class="card h-100 border-0 shadow-sm">
                                <div class="card-body">
                                    <p class="text-uppercase small text-muted mb-2">API Latency</p>
                                    <h4 class="fw-bold mb-1">142ms</h4>
                                    <span class="text-success small">Normal</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="border-top pt-4">
                        <h6 class="fw-semibold mb-3">Recent System Alerts</h6>
                        <div class="list-group list-group-flush">
                            <div class="list-group-item px-0 py-3">
                                <div class="d-flex align-items-center gap-3">
                                    <span class="badge bg-danger rounded-circle p-2"><i
                                            class="ti tabler-alert-triangle"></i></span>
                                    <div class="flex-grow-1">
                                        <p class="mb-1 fw-semibold">BlueCross Portal authentication expired (Provider
                                            #882)</p>
                                        <small class="text-muted">2 mins ago</small>
                                    </div>
                                </div>
                            </div>
                            <div class="list-group-item px-0 py-3">
                                <div class="d-flex align-items-center gap-3">
                                    <span class="badge bg-warning text-dark rounded-circle p-2"><i
                                            class="ti tabler-alert-circle"></i></span>
                                    <div class="flex-grow-1">
                                        <p class="mb-1 fw-semibold">OCR Engine processing queue exceeding threshold</p>
                                        <small class="text-muted">15 mins ago</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-4">
            <div class="row g-4">
                <div class="col-12">
                    <div class="card shadow-sm border-0 h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-start gap-3 mb-3">
                                <span class="badge bg-info text-white rounded-3 p-2"><i
                                        class="ti tabler-mail"></i></span>
                                <div class="flex-grow-1">
                                    <h6 class="fw-bold mb-1">Mail / SMTP</h6>
                                    <p class="text-muted mb-2">Configure Office 365 SMTP for outbound credentialing emails.</p>
                                    <a href="{{ route('admin.settings.mail') }}" class="btn btn-sm btn-outline-primary">Configure Mail</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-12">
                    <div class="card shadow-sm border-0 h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-start gap-3 mb-3">
                                <span class="badge bg-primary text-white rounded-3 p-2"><i
                                        class="ti tabler-users"></i></span>
                                <div class="flex-grow-1">
                                    <h6 class="fw-bold mb-1">User Management</h6>
                                    <p class="text-muted mb-2">Manage directory users, assign roles, and set granular
                                        access permissions.</p>
                                </div>
                            </div>
                            <div class="d-flex flex-wrap gap-2">
                                <span class="badge bg-light text-dark">82 Active Users</span>
                                <span class="badge bg-light text-dark">4 Custom Roles</span>
                            </div>
                        </div>
                    </div>
                </div>
                @if (auth('admin')->user()?->can('admin.portal-credentials.manage'))
                <div class="col-12">
                    <div class="card shadow-sm border-0 h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-start gap-3 mb-3">
                                <span class="badge bg-secondary text-white rounded-3 p-2"><i
                                        class="ti tabler-lock"></i></span>
                                <div class="flex-grow-1">
                                    <h6 class="fw-bold mb-1">Portal Accounts</h6>
                                    <p class="text-muted mb-2">Reset practice and provider logins for the credentialing portal.</p>
                                    <a href="{{ route('admin.settings.portal-accounts') }}" class="btn btn-sm btn-outline-primary">Manage Portal Accounts</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                @endif
                <div class="col-12">
                    <div class="card shadow-sm border-0 h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-start gap-3 mb-3">
                                <span class="badge bg-success text-white rounded-3 p-2"><i
                                        class="ti tabler-shield-check"></i></span>
                                <div class="flex-grow-1">
                                    <h6 class="fw-bold mb-1">Clinical Compliance</h6>
                                    <p class="text-muted mb-2">Automated verification rules, state document
                                        requirements, and sanction checks.</p>
                                </div>
                            </div>
                            <span class="badge bg-success">RULE-SET v4.2 ACTIVE</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-xl-6">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <div>
                            <h6 class="fw-bold mb-1">Notification Triggers</h6>
                            <p class="text-muted mb-0">SLA alerts, automated email schedules, and escalations.</p>
                        </div>
                        <a href="#" class="btn btn-sm btn-outline-primary">Configure</a>
                    </div>
                    <div class="border rounded-3 p-3">
                        <div class="mb-3">
                            <div class="small text-uppercase text-muted mb-1">Critical SLA</div>
                            <div class="fw-semibold">Email + SMS @ 2h</div>
                        </div>
                        <div>
                            <div class="small text-uppercase text-muted mb-1">Provider Update</div>
                            <div class="fw-semibold">Weekly Digest</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-6">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <div>
                            <h6 class="fw-bold mb-1">System Integrations</h6>
                            <p class="text-muted mb-0">Payer portals, OCR engine parameters, and Webhooks.</p>
                        </div>
                        <a href="#" class="btn btn-sm btn-primary">Manage</a>
                    </div>
                    <div class="d-flex flex-wrap align-items-center gap-2">
                        <span class="badge bg-secondary">API</span>
                        <span class="badge bg-secondary">AWI</span>
                        <span class="badge bg-secondary">CRM</span>
                        <span class="text-muted small">8 active third-party endpoints connected</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-xl-6">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <div>
                            <h6 class="fw-bold mb-1">Security, Encryption & Record Retention</h6>
                            <p class="text-muted small mb-0">Configure HIPAA retention windows, password rotation, and document encryption at rest.</p>
                        </div>
                        <span class="badge bg-label-primary">Security Policy</span>
                    </div>
                    <form wire:submit.prevent="saveSecuritySettings">
                        <div class="row g-3 mb-3">
                            <div class="col-sm-6">
                                <label class="form-label small fw-semibold">Record Retention (Days)</label>
                                <input type="number" min="1" max="3650" wire:model="record_retention_days"
                                    class="form-control form-control-sm @error('record_retention_days') is-invalid @enderror">
                                @error('record_retention_days')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label small fw-semibold">Password Expiry (Days)</label>
                                <input type="number" min="1" max="365" wire:model="password_expiry_days"
                                    class="form-control form-control-sm @error('password_expiry_days') is-invalid @enderror">
                                @error('password_expiry_days')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-12">
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" id="encrypt_documents_at_rest"
                                        wire:model="encrypt_documents_at_rest">
                                    <label class="form-check-label small" for="encrypt_documents_at_rest">
                                        Encrypt newly uploaded document files at rest (AES-256)
                                    </label>
                                </div>
                            </div>
                        </div>
                        <div class="d-flex flex-wrap gap-2">
                            <button type="submit" class="btn btn-sm btn-primary">
                                <i class="ti tabler-device-floppy me-1"></i> Save Security Policy
                            </button>
                            <button type="button" wire:click="runRetentionNow" class="btn btn-sm btn-outline-secondary">
                                <i class="ti tabler-trash me-1"></i> Run Retention Cleanup Now
                            </button>
                            <button type="button" wire:click="downloadBackupSnapshot" class="btn btn-sm btn-outline-info">
                                <i class="ti tabler-download me-1"></i> Download Backup Snapshot
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-xl-6">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <div>
                            <h6 class="fw-bold mb-1">Production Go-Live Readiness</h6>
                            <p class="text-muted small mb-0">Automated verification of database views, encryption, TLS, storage, and mail configuration.</p>
                        </div>
                        <span class="badge bg-label-success">Checklist</span>
                    </div>
                    <div class="list-group list-group-flush">
                        @foreach ($readinessChecks as $check)
                            <div class="list-group-item px-0 py-2 d-flex align-items-center justify-content-between">
                                <div>
                                    <div class="fw-semibold small">{{ $check['label'] }}</div>
                                    <div class="text-muted" style="font-size: 12px;">{{ $check['detail'] }}</div>
                                </div>
                                <span class="badge {{ $check['passed'] ? 'bg-label-success' : 'bg-label-warning' }}">
                                    {{ $check['passed'] ? 'Ready' : 'Review' }}
                                </span>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-white border-bottom py-3 d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-2">
            <div>
                <h5 class="fw-bold mb-0">Audit Compliance Logs</h5>
                <small class="text-muted">Ensure regulatory adherence with full-spectrum audit trails for logins, downloads, deletes, exports, and overrides.</small>
            </div>
            <div class="d-flex gap-2">
                <input type="search" wire:model.live.debounce.250ms="auditSearch"
                    class="form-control form-control-sm" style="min-width: 240px;"
                    placeholder="Search action, entity, or admin...">
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Timestamp</th>
                        <th>Action</th>
                        <th>Admin</th>
                        <th>Target Entity</th>
                        <th>Metadata</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($auditLogs as $log)
                        <tr>
                            <td class="text-muted small text-nowrap">{{ $log->created_at?->format('Y-m-d H:i:s') }}</td>
                            <td><span class="badge bg-label-primary">{{ $log->action }}</span></td>
                            <td class="small">{{ $log->admin?->name ?? ($log->admin_id ? 'Admin #' . $log->admin_id : 'System') }}</td>
                            <td class="small text-muted">
                                @if ($log->auditable_type)
                                    {{ class_basename($log->auditable_type) }} #{{ $log->auditable_id }}
                                @else
                                    —
                                @endif
                            </td>
                            <td class="small text-muted">
                                @if (!empty($log->metadata))
                                    <code>{{ \Illuminate\Support\Str::limit(json_encode($log->metadata), 90) }}</code>
                                @else
                                    —
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center py-4 text-muted small">No audit log entries match the current filter.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
