<div class="container-fluid flex-grow-1 px-3 px-md-4 py-3 py-md-4">

    <div class="row g-4 mb-5">
        <div class="col-12">
            <div class="card shadow-sm border-0">
                <div class="card-body d-flex flex-column flex-md-row justify-content-between align-items-start gap-3">
                    <div>
                        <h3 class="fw-bold text-primary mb-2">Provider Management</h3>
                        <p class="text-muted mb-0">Oversee and maintain credentialing compliance across your network.</p>
                    </div>
                    <div class="d-flex flex-column flex-sm-row gap-2">
                        <a href="#" class="btn btn-primary">
                            <i class="ti tabler-file-plus me-1"></i> New Application
                        </a>
                        <a href="#" class="btn btn-outline-secondary">
                            Export Report
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-md-3">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <div>
                            <p class="text-uppercase text-muted small mb-1">Total Providers</p>
                        </div>
                        <span class="badge bg-label-success">+4%</span>
                    </div>
                    <h3 class="fw-bold mb-0">1,284</h3>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-md-3">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <div>
                            <p class="text-uppercase text-muted small mb-1">Compliance Rate</p>
                        </div>
                        <span class="badge bg-label-warning">In-Progress</span>
                    </div>
                    <h3 class="fw-bold mb-0">94.2%</h3>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-md-3">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <div>
                            <p class="text-uppercase text-muted small mb-1">Pending Payers</p>
                        </div>
                        <span class="badge bg-label-info">Action Needed</span>
                    </div>
                    <h3 class="fw-bold mb-0">312</h3>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-md-3">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <div>
                            <p class="text-uppercase text-muted small mb-1">Expiry Alerts</p>
                        </div>
                        <span class="badge bg-danger">Critical</span>
                    </div>
                    <h3 class="fw-bold mb-0">14</h3>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body">
            <div class="row gy-3 gx-2 align-items-center">
                <div class="col-lg-5">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-white border-end-0"><i class="ti tabler-search"></i></span>
                        <input type="text" class="form-control border-start-0"
                            placeholder="Filter by name, specialty or practice..." />
                    </div>
                </div>
                <div class="col-auto">
                    <button type="button" class="btn btn-outline-secondary btn-sm">
                        <i class="ti tabler-map-pin"></i>
                        All States
                    </button>
                </div>
                <div class="col-auto">
                    <button type="button" class="btn btn-outline-secondary btn-sm">
                        <i class="ti tabler-building"></i>
                        All Payers
                    </button>
                </div>
                <div class="col-auto">
                    <button type="button" class="btn btn-outline-secondary btn-sm">
                        <i class="ti tabler-activity-heartbeat"></i>
                        Status: All
                    </button>
                </div>
                <div class="col-auto ms-auto">
                    <button type="button" class="btn btn-outline-secondary btn-sm">
                        <i class="ti tabler-download"></i>
                        Export
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm border-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th scope="col" style="width: 40px;">
                            <input class="form-check-input" type="checkbox" />
                        </th>
                        <th scope="col">Provider Name</th>
                        <th scope="col">NPI</th>
                        <th scope="col">Specialty</th>
                        <th scope="col">Practice</th>
                        <th scope="col">Status</th>
                        <th scope="col">Pending Payers</th>
                        <th scope="col">Alerts</th>
                        <th scope="col" class="text-end">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><input class="form-check-input" type="checkbox" /></td>
                        <td>
                            <div class="d-flex align-items-center gap-3">
                                <span class="avatar rounded-circle bg-primary text-white fw-bold">JS</span>
                                <div>
                                    <div class="fw-semibold">Dr. Julianne Smith</div>
                                    <small class="text-muted">Cardiology</small>
                                </div>
                            </div>
                        </td>
                        <td>1092837465</td>
                        <td>Cardiology</td>
                        <td>North Shore Heart Center</td>
                        <td><span class="badge bg-label-success text-uppercase">Approved</span></td>
                        <td>0</td>
                        <td><i class="ti tabler-circle-check text-success"></i></td>
                        <td class="text-end"><button class="btn btn-sm btn-outline-primary">View</button></td>
                    </tr>
                    <tr>
                        <td><input class="form-check-input" type="checkbox" /></td>
                        <td>
                            <div class="d-flex align-items-center gap-3">
                                <span class="avatar rounded-circle bg-secondary text-white fw-bold">RW</span>
                                <div>
                                    <div class="fw-semibold">Dr. Robert Wright</div>
                                    <small class="text-muted">Neurology</small>
                                </div>
                            </div>
                        </td>
                        <td>1283746509</td>
                        <td>Neurology</td>
                        <td>Riverside Medical Group</td>
                        <td><span class="badge bg-label-warning text-uppercase">Pending</span></td>
                        <td>4</td>
                        <td><i class="ti tabler-alert-triangle text-warning"></i></td>
                        <td class="text-end"><button class="btn btn-sm btn-outline-primary">View</button></td>
                    </tr>
                    <tr>
                        <td><input class="form-check-input" type="checkbox" /></td>
                        <td>
                            <div class="d-flex align-items-center gap-3">
                                <span class="avatar rounded-circle bg-success text-white fw-bold">EM</span>
                                <div>
                                    <div class="fw-semibold">Dr. Elena Moreno</div>
                                    <small class="text-muted">Pediatrics</small>
                                </div>
                            </div>
                        </td>
                        <td>1472583690</td>
                        <td>Pediatrics</td>
                        <td>City Kids Clinic</td>
                        <td><span class="badge bg-label-danger text-uppercase">Overdue</span></td>
                        <td>2</td>
                        <td><i class="ti tabler-alert-circle text-danger"></i></td>
                        <td class="text-end"><button class="btn btn-sm btn-outline-primary">View</button></td>
                    </tr>
                    <tr>
                        <td><input class="form-check-input" type="checkbox" /></td>
                        <td>
                            <div class="d-flex align-items-center gap-3">
                                <span class="avatar rounded-circle bg-info text-white fw-bold">ML</span>
                                <div>
                                    <div class="fw-semibold">Dr. Marcus Lee</div>
                                    <small class="text-muted">Orthopedics</small>
                                </div>
                            </div>
                        </td>
                        <td>1593572468</td>
                        <td>Orthopedics</td>
                        <td>Peak Sports Medicine</td>
                        <td><span class="badge bg-label-success text-uppercase">Approved</span></td>
                        <td>1</td>
                        <td><i class="ti tabler-info-circle text-secondary"></i></td>
                        <td class="text-end"><button class="btn btn-sm btn-outline-primary">View</button></td>
                    </tr>
                    <tr>
                        <td><input class="form-check-input" type="checkbox" /></td>
                        <td>
                            <div class="d-flex align-items-center gap-3">
                                <span class="avatar rounded-circle bg-danger text-white fw-bold">SC</span>
                                <div>
                                    <div class="fw-semibold">Dr. Sarah Chen</div>
                                    <small class="text-muted">Internal Medicine</small>
                                </div>
                            </div>
                        </td>
                        <td>1928374655</td>
                        <td>Internal Medicine</td>
                        <td>Downtown Care Center</td>
                        <td><span class="badge bg-label-warning text-uppercase">Re-Cred</span></td>
                        <td>8</td>
                        <td><i class="ti tabler-bell text-danger"></i></td>
                        <td class="text-end"><button class="btn btn-sm btn-outline-primary">View</button></td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div class="card-footer bg-white border-top py-3">
            <div class="d-flex flex-column flex-md-row align-items-center justify-content-between gap-3">
                <div class="text-muted">Showing 1-10 of 1,284 providers</div>
                <nav>
                    <ul class="pagination pagination-sm mb-0">
                        <li class="page-item disabled"><a class="page-link" href="#">&laquo;</a></li>
                        <li class="page-item active"><a class="page-link" href="#">1</a></li>
                        <li class="page-item"><a class="page-link" href="#">2</a></li>
                        <li class="page-item"><a class="page-link" href="#">3</a></li>
                        <li class="page-item"><a class="page-link" href="#">129</a></li>
                        <li class="page-item"><a class="page-link" href="#">&raquo;</a></li>
                    </ul>
                </nav>
            </div>
        </div>
    </div>

</div>
