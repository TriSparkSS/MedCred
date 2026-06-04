<div class="container-fluid flex-grow-1 px-3 px-md-4 py-3 py-md-4">

    <div class="row g-4 mb-5">
        <div class="col-12">
            <div class="card shadow-sm border-0">
                <div class="card-body d-flex flex-column flex-md-row justify-content-between align-items-start gap-3">
                    <div>
                        <h3 class="fw-bold text-primary mb-2">Credentialing Tracker</h3>
                        <p class="text-muted mb-0">Monitoring 142 active provider enrollment applications across 12 payers.</p>
                    </div>
                    <div class="d-flex flex-column flex-sm-row gap-2">
                        <a href="#" class="btn btn-primary">
                            <i class="ti tabler-adjustments-horizontal me-1"></i>  Filters
                        </a>
                        <a href="#" class="btn btn-outline-secondary">
                            <i class="ti tabler-plus me-1"></i>
                            New App
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-lg-3">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body">
                    <p class="text-uppercase text-muted small mb-1">Total Active Apps</p>
                    <div class="d-flex align-items-end justify-content-between">
                        <h3 class="fw-bold mb-0">142</h3>
                        <span class="text-success small">+4%</span>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body">
                    <p class="text-uppercase text-muted small mb-1">Pending Provider</p>
                    <div class="d-flex align-items-end justify-content-between">
                        <h3 class="fw-bold mb-0">24</h3>
                        <span class="text-danger small">-2%</span>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body">
                    <p class="text-uppercase text-muted small mb-1">Pending Payer</p>
                    <div class="d-flex align-items-end justify-content-between">
                        <h3 class="fw-bold mb-0">58</h3>
                        <span class="text-muted small">Stable</span>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="card shadow-sm border-0 h-100 border-danger">
                <div class="card-body">
                    <p class="text-uppercase text-muted small mb-1">Overdue</p>
                    <div class="d-flex align-items-end justify-content-between">
                        <h3 class="fw-bold mb-0">12</h3>
                        <span class="text-danger small">+3%</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-12">
            <div class="card shadow-sm border-0">
                <div class="card-body pb-0">
                    <div
                        class="d-flex flex-column flex-sm-row align-items-start align-items-sm-center justify-content-between gap-3 mb-4">
                        <div>
                            <h5 class="mb-1">Applications</h5>
                            <p class="text-muted mb-0">Review active credentialing workflows and status updates.</p>
                        </div>
                        <div class="d-flex flex-wrap gap-2">
                            <button class="btn btn-outline-secondary btn-sm">
                                <i class="ti tabler-search me-1"></i>
                                Search
                            </button>
                            <button class="btn btn-outline-secondary btn-sm">
                                <i class="ti tabler-calendar me-1"></i>
                                Last 30 Days
                            </button>
                        </div>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th scope="col" style="width: 40px;"><input class="form-check-input"
                                        type="checkbox" /></th>
                                <th scope="col">Provider</th>
                                <th scope="col">Payer</th>
                                <th scope="col">Status</th>
                                <th scope="col">Progress</th>
                                <th scope="col">Due</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><input class="form-check-input" type="checkbox" /></td>
                                <td>
                                    <div class="d-flex align-items-center gap-3">
                                        <span class="avatar rounded-circle bg-primary text-white fw-bold">JM</span>
                                        <div>
                                            <div class="fw-semibold">Dr. Julianne Moore</div>
                                            <small class="text-muted">BCBS - Commercial Enrollment</small>
                                        </div>
                                    </div>
                                </td>
                                <td>Blue Cross Blue Shield</td>
                                <td><span class="badge bg-label-info text-uppercase">Pending Doc</span></td>
                                <td style="min-width: 180px;">
                                    <div class="progress rounded-pill" style="height:8px;">
                                        <div class="progress-bar bg-success" style="width: 85%;"></div>
                                    </div>
                                </td>
                                <td>Oct 25</td>
                            </tr>
                            <tr>
                                <td><input class="form-check-input" type="checkbox" /></td>
                                <td>
                                    <div class="d-flex align-items-center gap-3">
                                        <span class="avatar rounded-circle bg-secondary text-white fw-bold">AS</span>
                                        <div>
                                            <div class="fw-semibold">Dr. Aaron Swartz</div>
                                            <small class="text-muted">Aetna Commercial</small>
                                        </div>
                                    </div>
                                </td>
                                <td>Aetna Commercial</td>
                                <td><span class="badge bg-label-warning text-uppercase">Review</span></td>
                                <td>
                                    <div class="progress rounded-pill" style="height:8px;">
                                        <div class="progress-bar bg-info" style="width: 68%;"></div>
                                    </div>
                                </td>
                                <td>Oct 28</td>
                            </tr>
                            <tr>
                                <td><input class="form-check-input" type="checkbox" /></td>
                                <td>
                                    <div class="d-flex align-items-center gap-3">
                                        <span class="avatar rounded-circle bg-success text-white fw-bold">ER</span>
                                        <div>
                                            <div class="fw-semibold">Dr. Elena Rodriguez</div>
                                            <small class="text-muted">UnitedHealthcare</small>
                                        </div>
                                    </div>
                                </td>
                                <td>UnitedHealthcare</td>
                                <td><span class="badge bg-label-danger text-uppercase">Escalated</span></td>
                                <td>
                                    <div class="progress rounded-pill" style="height:8px;">
                                        <div class="progress-bar bg-danger" style="width: 42%;"></div>
                                    </div>
                                </td>
                                <td>Oct 26</td>
                            </tr>
                            <tr>
                                <td><input class="form-check-input" type="checkbox" /></td>
                                <td>
                                    <div class="d-flex align-items-center gap-3">
                                        <span class="avatar rounded-circle bg-info text-white fw-bold">MC</span>
                                        <div>
                                            <div class="fw-semibold">Dr. Michael Chen</div>
                                            <small class="text-muted">Medicare Part B</small>
                                        </div>
                                    </div>
                                </td>
                                <td>Medicare Part B</td>
                                <td><span class="badge bg-label-success text-uppercase">Submitted</span></td>
                                <td>
                                    <div class="progress rounded-pill" style="height:8px;">
                                        <div class="progress-bar bg-success" style="width: 100%;"></div>
                                    </div>
                                </td>
                                <td>Oct 22</td>
                            </tr>
                            <tr>
                                <td><input class="form-check-input" type="checkbox" /></td>
                                <td>
                                    <div class="d-flex align-items-center gap-3">
                                        <span class="avatar rounded-circle bg-warning text-white fw-bold">SC</span>
                                        <div>
                                            <div class="fw-semibold">Dr. Sarah Chen</div>
                                            <small class="text-muted">Medicare Part B</small>
                                        </div>
                                    </div>
                                </td>
                                <td>Medicare Part B</td>
                                <td><span class="badge bg-label-success text-uppercase">Submitted</span></td>
                                <td>
                                    <div class="progress rounded-pill" style="height:8px;">
                                        <div class="progress-bar bg-success" style="width: 100%;"></div>
                                    </div>
                                </td>
                                <td>Oct 23</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="card-footer bg-white border-top py-3">
                    <div class="d-flex flex-column flex-md-row align-items-center justify-content-between gap-3">
                        <div class="text-muted">Showing 1-10 of 142 applications</div>
                        <nav>
                            <ul class="pagination pagination-sm mb-0">
                                <li class="page-item disabled"><a class="page-link" href="#">«</a></li>
                                <li class="page-item active"><a class="page-link" href="#">1</a></li>
                                <li class="page-item"><a class="page-link" href="#">2</a></li>
                                <li class="page-item"><a class="page-link" href="#">3</a></li>
                                <li class="page-item"><a class="page-link" href="#">»</a></li>
                            </ul>
                        </nav>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="credentialApplicationModal" tabindex="-1"
        aria-labelledby="credentialApplicationModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header border-0">
                    <div>
                        <small class="text-uppercase text-muted">Application ID</small>
                        <h5 class="modal-title fw-bold" id="credentialApplicationModalLabel">APP-9821</h5>
                        <div class="text-muted">Dr. Julianne Moore</div>
                        <div class="small text-muted">BCBS - Commercial Enrollment</div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="border rounded-3 p-3 mb-4 bg-light">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <div>
                                <small class="text-uppercase text-muted">Next action required</small>
                                <p class="mb-0 fw-semibold">Request DEA Certificate Update from provider office.</p>
                            </div>
                        </div>
                        <button class="btn btn-primary btn-sm w-100">
                            Generate Request Email
                        </button>
                    </div>

                    <div class="mb-4">
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <h6 class="mb-0">Application Timeline</h6>
                            <span class="text-success small">In Progress</span>
                        </div>
                        <ul class="timeline list-unstyled mb-0">
                            <li class="d-flex align-items-start mb-3">
                                <span class="badge bg-success rounded-circle me-3"
                                    style="width:10px; height:10px;"></span>
                                <div>
                                    <div class="fw-semibold">Application Submitted</div>
                                    <small class="text-muted">Oct 12, 2023 · System User</small>
                                </div>
                            </li>
                            <li class="d-flex align-items-start mb-3">
                                <span class="badge bg-primary rounded-circle me-3"
                                    style="width:10px; height:10px;"></span>
                                <div>
                                    <div class="fw-semibold">Receipt Confirmed by Payer</div>
                                    <small class="text-muted">Oct 15, 2023 · Payer Portal</small>
                                </div>
                            </li>
                            <li class="d-flex align-items-start mb-3">
                                <span class="badge bg-warning rounded-circle me-3"
                                    style="width:10px; height:10px;"></span>
                                <div>
                                    <div class="fw-semibold">Request for Clarification</div>
                                    <small class="text-muted">Oct 22, 2023 · Payer Rep: 'Missing DEA'</small>
                                </div>
                            </li>
                        </ul>
                    </div>

                    <div class="mb-4">
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <h6 class="mb-0">Document Checklist</h6>
                            <span class="text-muted small">75% complete</span>
                        </div>
                        <div class="list-group list-group-flush">
                            <div class="list-group-item px-0 border-0 pb-3">
                                <div class="d-flex align-items-center justify-content-between mb-2">
                                    <div>
                                        <div class="fw-semibold">State License (2024 Exp)</div>
                                        <small class="text-muted">Verified</small>
                                    </div>
                                    <i class="ti tabler-eye text-muted"></i>
                                </div>
                            </div>
                            <div class="list-group-item px-0 border-0 pb-3">
                                <div class="d-flex align-items-center justify-content-between mb-2">
                                    <div>
                                        <div class="fw-semibold">Board Certification</div>
                                        <small class="text-muted">In Progress</small>
                                    </div>
                                    <i class="ti tabler-eye text-muted"></i>
                                </div>
                            </div>
                            <div class="list-group-item px-0 border-0 pb-3">
                                <div class="d-flex align-items-center justify-content-between">
                                    <div>
                                        <div class="fw-semibold">DEA Certificate</div>
                                        <small class="text-danger">Rejected - Re-upload needed</small>
                                    </div>
                                    <button class="btn btn-sm btn-outline-danger">Request</button>
                                </div>
                            </div>
                            <div class="list-group-item px-0 border-0">
                                <div class="d-flex align-items-center justify-content-between">
                                    <div>
                                        <div class="fw-semibold">Malpractice History</div>
                                        <small class="text-muted">Verified</small>
                                    </div>
                                    <i class="ti tabler-eye text-muted"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="position-relative">
                        <textarea class="form-control form-control-sm mb-3" rows="3" placeholder="Internal note..."></textarea>
                        <button
                            class="btn btn-primary btn-sm position-absolute end-0 bottom-0 translate-middle-y me-2">
                            <i class="ti tabler-send"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
