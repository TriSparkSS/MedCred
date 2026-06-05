<div class="container-fluid flex-grow-1 px-3 px-md-4 py-3 py-md-4">
    <div class="row g-4 mb-4">
        <div class="col-12">
            <div class="card shadow-sm border-0">
                <div class="card-body d-flex flex-column flex-md-row justify-content-between align-items-start gap-3">
                    <div>
                        <h3 class="fw-bold text-primary mb-2">Reports</h3>
                        <p class="text-muted mb-0">Generate, review, and export operational and compliance reports across
                            the platform.</p>
                    </div>
                    <div class="d-flex flex-column flex-sm-row gap-2">
                        <a href="#" class="btn btn-primary">
                            <i class="ti tabler-file-plus me-1"></i> New Report
                        </a>
                        <a href="#" class="btn btn-outline-secondary">
                            <i class="ti tabler-download me-1"></i> Export List
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-lg-4 col-md-6">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body">
                    <p class="text-uppercase text-muted small mb-2">Total Reports</p>
                    <div class="d-flex align-items-center justify-content-between">
                        <h3 class="fw-bold mb-0">184</h3>
                        <span class="badge bg-label-success">Updated</span>
                    </div>
                    <p class="text-muted mb-0 mt-3">Includes scheduled, adhoc, and compliance reports.</p>
                </div>
            </div>
        </div>
        <div class="col-lg-4 col-md-6">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body">
                    <p class="text-uppercase text-muted small mb-2">Pending Delivery</p>
                    <div class="d-flex align-items-center justify-content-between">
                        <h3 class="fw-bold mb-0">26</h3>
                        <span class="text-success small">On schedule</span>
                    </div>
                    <p class="text-muted mb-0 mt-3">Awaiting export or recipients confirmation.</p>
                </div>
            </div>
        </div>
        <div class="col-lg-4 col-md-12">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body">
                    <p class="text-uppercase text-muted small mb-2">Last Generated</p>
                    <div class="d-flex align-items-center justify-content-between">
                        <h3 class="fw-bold mb-0">Today</h3>
                        <span class="text-muted small">2:14 PM</span>
                    </div>
                    <p class="text-muted mb-0 mt-3">Latest report output from the system compliance engine.</p>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body">
            <div class="row g-3 align-items-center mb-3">
                <div class="col-md-8 col-lg-9">
                    <div class="input-group">
                        <span class="input-group-text bg-white border-end-0"><i class="ti tabler-search"></i></span>
                        <input type="search" class="form-control border-start-0"
                            placeholder="Search reports by name, owner, or type">
                    </div>
                </div>
                <div class="col-md-4 col-lg-3 d-flex gap-2 justify-content-md-end">
                    <select class="form-select">
                        <option selected>Filter by status</option>
                        <option>Scheduled</option>
                        <option>Completed</option>
                        <option>Failed</option>
                        <option>In Progress</option>
                    </select>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-borderless align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Report name</th>
                            <th>Type</th>
                            <th>Owner</th>
                            <th>Status</th>
                            <th>Last run</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td class="fw-semibold">Monthly Compliance Summary</td>
                            <td>Compliance</td>
                            <td>System</td>
                            <td><span class="badge bg-success">Completed</span></td>
                            <td>Today, 9:18 AM</td>
                            <td class="text-end"><a href="#" class="btn btn-sm btn-outline-secondary">View</a>
                            </td>
                        </tr>
                        <tr>
                            <td class="fw-semibold">Provider Credentialing Audit</td>
                            <td>Audit</td>
                            <td>Admin</td>
                            <td><span class="badge bg-warning text-dark">In Progress</span></td>
                            <td>Today, 7:12 AM</td>
                            <td class="text-end"><a href="#" class="btn btn-sm btn-outline-secondary">View</a>
                            </td>
                        </tr>
                        <tr>
                            <td class="fw-semibold">Accounts Receivable Overview</td>
                            <td>Financial</td>
                            <td>Finance</td>
                            <td><span class="badge bg-label-secondary">Scheduled</span></td>
                            <td>Tomorrow, 6:00 AM</td>
                            <td class="text-end"><a href="#" class="btn btn-sm btn-outline-secondary">View</a>
                            </td>
                        </tr>
                        <tr>
                            <td class="fw-semibold">OCR Processing Throughput</td>
                            <td>Performance</td>
                            <td>Operations</td>
                            <td><span class="badge bg-danger">Failed</span></td>
                            <td>Yesterday, 5:04 PM</td>
                            <td class="text-end"><a href="#" class="btn btn-sm btn-outline-secondary">Retry</a>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-xl-6">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body">
                    <h6 class="fw-semibold mb-3">Report Templates</h6>
                    <p class="text-muted mb-4">Start with predefined templates for compliance, financial, and
                        operational summaries.</p>
                    <div class="list-group list-group-flush">
                        <div class="list-group-item px-0 py-3 d-flex justify-content-between align-items-start gap-3">
                            <div>
                                <p class="fw-semibold mb-1">Weekly Provider Status</p>
                                <small class="text-muted">Dashboard, provider performance, and open issues.</small>
                            </div>
                            <span class="badge bg-label-primary">Template</span>
                        </div>
                        <div class="list-group-item px-0 py-3 d-flex justify-content-between align-items-start gap-3">
                            <div>
                                <p class="fw-semibold mb-1">Service Level Exceptions</p>
                                <small class="text-muted">Escalation activity and SLA compliance records.</small>
                            </div>
                            <span class="badge bg-label-primary">Template</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-6">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body">
                    <h6 class="fw-semibold mb-3">Recent Exports</h6>
                    <p class="text-muted mb-4">Quick access to the most recent report exports and downloads.</p>
                    <div class="d-flex flex-column gap-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <p class="fw-semibold mb-1">Provider Aggregation Report</p>
                                <small class="text-muted">Exported 1 hour ago</small>
                            </div>
                            <span class="badge bg-light text-dark">CSV</span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <p class="fw-semibold mb-1">Credentialing Progress</p>
                                <small class="text-muted">Exported yesterday</small>
                            </div>
                            <span class="badge bg-light text-dark">PDF</span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <p class="fw-semibold mb-1">SLA Compliance Summary</p>
                                <small class="text-muted">Exported 3 days ago</small>
                            </div>
                            <span class="badge bg-light text-dark">XLSX</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
