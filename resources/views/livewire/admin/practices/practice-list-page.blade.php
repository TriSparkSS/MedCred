<div class="container-fluid flex-grow-1 px-3 px-md-4 py-3 py-md-4">

     <div class="row g-4 mb-5">
        <div class="col-12">
            <div class="card shadow-sm border-0">
                <div class="card-body d-flex flex-column flex-md-row justify-content-between align-items-start gap-3">
                    <div>
                        <h3 class="fw-bold text-primary mb-2">Practice Management</h3>
                        <p class="text-muted mb-0">Oversee and maintain credentialing compliance across your network.</p>
                    </div>
                    <div class="d-flex flex-column flex-sm-row gap-2">
                        <a href="#" class="btn btn-primary">
                            <i class="ti tabler-file-plus me-1"></i> Add New Practice
                        </a>
                        {{-- <a href="#" class="btn btn-outline-secondary">
                            Export Report
                        </a> --}}
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-md-4 col-xl-3">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <div>
                            <p class="text-uppercase text-muted small mb-1">Total Practices</p>
                        </div>
                        <span class="badge bg-label-success">+4% this month</span>
                    </div>
                    <h3 class="fw-bold mb-0">142</h3>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-md-4 col-xl-3">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <div>
                            <p class="text-uppercase text-muted small mb-1">Average Compliance Rate</p>
                        </div>
                        <span class="badge bg-label-success">92.4%</span>
                    </div>
                    <div class="progress rounded-pill" style="height:8px;">
                        <div class="progress-bar bg-success" style="width:92.4%"></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-md-4 col-xl-3">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <div>
                            <p class="text-uppercase text-muted small mb-1">Pending Payers</p>
                        </div>
                        <span class="badge bg-label-warning">Action Needed</span>
                    </div>
                    <h3 class="fw-bold mb-0">18</h3>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-md-4 col-xl-3">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <div>
                            <p class="text-uppercase text-muted small mb-1">Expiring Facility Licenses (30D)</p>
                        </div>
                        <span class="badge bg-danger">Critical</span>
                    </div>
                    <h3 class="fw-bold mb-0">18</h3>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body">
            <div class="row gx-2 gy-2 align-items-center">
                <div class="col-auto">
                    <div class="dropdown">
                        <button class="btn btn-outline-secondary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                            All States
                        </button>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="#">All States</a></li>
                            <li><a class="dropdown-item" href="#">California</a></li>
                            <li><a class="dropdown-item" href="#">Texas</a></li>
                        </ul>
                    </div>
                </div>
                <div class="col-auto">
                    <div class="dropdown">
                        <button class="btn btn-outline-secondary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                            All Types
                        </button>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="#">All Types</a></li>
                            <li><a class="dropdown-item" href="#">Multispecialty</a></li>
                            <li><a class="dropdown-item" href="#">Urgent Care</a></li>
                        </ul>
                    </div>
                </div>
                <div class="col-auto">
                    <div class="dropdown">
                        <button class="btn btn-outline-secondary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                            All Statuses
                        </button>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="#">All Statuses</a></li>
                            <li><a class="dropdown-item" href="#">Compliant</a></li>
                            <li><a class="dropdown-item" href="#">In-Progress</a></li>
                        </ul>
                    </div>
                </div>
                <div class="col ms-auto text-end text-muted">
                    Showing 1-12 of 142 results
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm border-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th scope="col">Practice Name</th>
                        <th scope="col">Group/Type</th>
                        <th scope="col">Location</th>
                        <th scope="col">Providers</th>
                        <th scope="col">Active Apps</th>
                        <th scope="col">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td class="fw-semibold">Northwest Medical Group</td>
                        <td>Multispecialty</td>
                        <td>Seattle, WA</td>
                        <td>42</td>
                        <td>8</td>
                        <td><span class="badge bg-success">Compliant</span></td>
                    </tr>
                    <tr>
                        <td class="fw-semibold">Sunset Surgery Center</td>
                        <td>Surgery Center</td>
                        <td>Los Angeles, CA</td>
                        <td>12</td>
                        <td>3</td>
                        <td><span class="badge bg-warning text-dark">In-Progress</span></td>
                    </tr>
                    <tr>
                        <td class="fw-semibold">Metro Cardiology Assoc.</td>
                        <td>Single Specialty</td>
                        <td>Houston, TX</td>
                        <td>8</td>
                        <td>1</td>
                        <td><span class="badge bg-danger">Action Needed</span></td>
                    </tr>
                    <tr>
                        <td class="fw-semibold">Oakwood Pediatrics</td>
                        <td>Pediatrics</td>
                        <td>Chicago, IL</td>
                        <td>15</td>
                        <td>0</td>
                        <td><span class="badge bg-success">Compliant</span></td>
                    </tr>
                    <tr>
                        <td class="fw-semibold">Valley Urgent Care</td>
                        <td>Urgent Care</td>
                        <td>Phoenix, AZ</td>
                        <td>24</td>
                        <td>5</td>
                        <td><span class="badge bg-warning text-dark">In-Progress</span></td>
                    </tr>
                    <tr>
                        <td class="fw-semibold">Riverside Orthopedics</td>
                        <td>Specialty Group</td>
                        <td>Portland, OR</td>
                        <td>19</td>
                        <td>2</td>
                        <td><span class="badge bg-success">Compliant</span></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

</div>
