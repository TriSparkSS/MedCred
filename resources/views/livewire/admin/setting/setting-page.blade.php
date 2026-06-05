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

    <div class="card shadow-sm border-0 text-white"
        style="background: linear-gradient(135deg, #0d2a75 0%, #1f4fa9 100%);">
        <div class="card-body">
            <div class="row g-4 align-items-center">
                <div class="col-md-8">
                    <h5 class="fw-bold text-white mb-2">Audit Compliance Logs</h5>
                    <p class="text-white-75 mb-0">Ensure regulatory adherence with full-spectrum audit trails.
                        Revantage logs every action taken within the configuration suite for HIPAA compliance and
                        operational security.</p>
                </div>
                <div class="col-md-4 d-flex gap-2 flex-wrap justify-content-md-end">
                    <a href="#" class="btn btn-light">Download Annual Report</a>
                    <a href="#" class="btn btn-outline-light">Search Logs</a>
                </div>
            </div>
        </div>
    </div>
</div>
