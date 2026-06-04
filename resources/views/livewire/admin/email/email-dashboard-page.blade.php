<div class="container-fluid flex-grow-1 px-3 px-md-4 py-3 py-md-4">

    <div class="row g-4 mb-5">
        <div class="col-12">
            <div class="card shadow-sm border-0">
                <div class="card-body d-flex flex-column flex-md-row justify-content-between align-items-start gap-3">
                    <div>
                        <h3 class="fw-bold text-primary mb-2">Email Analytics</h3>
                        <p class="text-muted mb-0">Real-time engagement metrics for clinical correspondence.</p>
                    </div>
                    <div class="d-flex flex-column flex-sm-row gap-2">
                        <a href="#" class="btn btn-primary">
                            <i class="ti tabler-calendar me-1"></i>Last 30 Days
                        </a>
                        <a href="#" class="btn btn-outline-secondary">
                            <i class="ti tabler-download me-1"></i>
                            Export report
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-xl-3">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body">
                    <p class="text-uppercase text-muted small mb-1">Total Emails Sent</p>
                    <div class="d-flex align-items-center justify-content-between">
                        <h3 class="fw-bold mb-0">12,450</h3>
                        <span class="badge bg-label-success">+8%</span>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body">
                    <p class="text-uppercase text-muted small mb-1">Avg. Open Rate</p>
                    <div class="d-flex align-items-center justify-content-between">
                        <h3 class="fw-bold mb-0">68.2%</h3>
                        <span class="text-success small">Good</span>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body">
                    <p class="text-uppercase text-muted small mb-1">Click-to-Upload Rate</p>
                    <div class="d-flex align-items-center justify-content-between">
                        <h3 class="fw-bold mb-0">42.5%</h3>
                        <span class="text-success small">High</span>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body">
                    <p class="text-uppercase text-muted small mb-1">Bounce Rate</p>
                    <div class="d-flex align-items-center justify-content-between">
                        <h3 class="fw-bold mb-0">0.8%</h3>
                        <span class="text-success small">Healthy</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-xl-8">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between mb-4">
                        <div>
                            <h5 class="fw-bold mb-0">Email Engagement Trends</h5>
                            <p class="text-muted mb-0">Sent, opened, and action trends over the last month.</p>
                        </div>
                        <div class="d-flex gap-3">
                            <span class="d-inline-flex align-items-center gap-2">
                                <span class="rounded-circle" style="width:10px; height:10px; background:#0f172a"></span>
                                <small class="text-muted">Sent</small>
                            </span>
                            <span class="d-inline-flex align-items-center gap-2">
                                <span class="rounded-circle" style="width:10px; height:10px; background:#0b86ff"></span>
                                <small class="text-muted">Opened</small>
                            </span>
                            <span class="d-inline-flex align-items-center gap-2">
                                <span class="rounded-circle" style="width:10px; height:10px; background:#22c55e"></span>
                                <small class="text-muted">Actions</small>
                            </span>
                        </div>
                    </div>
                    <div class="chart-container" style="min-height: 320px;">
                        <div id="emailEngagementChart"></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-4">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between mb-4">
                        <div>
                            <h5 class="fw-bold mb-0">Communication Status</h5>
                            <p class="text-muted mb-0">Delivery health at a glance.</p>
                        </div>
                        <span class="badge bg-label-success">98.4% DELIVERY</span>
                    </div>
                    <div class="d-flex justify-content-center mb-4">
                        <div class="position-relative" style="width:220px; height:220px;">
                            <div class="rounded-circle border border-3 border-light"
                                style="width:220px; height:220px; position:absolute; top:0; left:0;"></div>
                            <div class="rounded-circle bg-white position-absolute"
                                style="width:170px; height:170px; top:25px; left:25px;"></div>
                            <div class="position-absolute top-50 start-50 translate-middle text-center">
                                <div class="fs-2 fw-bold">98.4%</div>
                                <div class="small text-muted">DELIVERY</div>
                            </div>
                        </div>
                    </div>
                    <div class="pt-2">
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <div class="d-flex align-items-center gap-2">
                                <span class="rounded-circle d-inline-block"
                                    style="width:10px; height:10px; background:#22c55e"></span>
                                <span class="text-muted">Delivered</span>
                            </div>
                            <span class="fw-semibold">12,250</span>
                        </div>
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <div class="d-flex align-items-center gap-2">
                                <span class="rounded-circle d-inline-block"
                                    style="width:10px; height:10px; background:#f59e0b"></span>
                                <span class="text-muted">Pending</span>
                            </div>
                            <span class="fw-semibold">102</span>
                        </div>
                        <div class="d-flex align-items-center justify-content-between">
                            <div class="d-flex align-items-center gap-2">
                                <span class="rounded-circle d-inline-block"
                                    style="width:10px; height:10px; background:#ef4444"></span>
                                <span class="text-muted">Failed</span>
                            </div>
                            <span class="fw-semibold">98</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-borderless mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Template Name</th>
                            <th>Emails Sent</th>
                            <th>Open Rate</th>
                            <th>Action Rate</th>
                            <th>Success Health</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td class="fw-semibold">Document Request</td>
                            <td>5,240</td>
                            <td>74.2%</td>
                            <td>58.1%</td>
                            <td><span class="badge bg-label-success">Optimal</span></td>
                        </tr>
                        <tr>
                            <td class="fw-semibold">Expiration Alert</td>
                            <td>3,120</td>
                            <td>82.5%</td>
                            <td>44.0%</td>
                            <td><span class="badge bg-label-success text-success">High Impact</span></td>
                        </tr>
                        <tr>
                            <td class="fw-semibold">Approval Confirmation</td>
                            <td>4,090</td>
                            <td>48.9%</td>
                            <td>25.3%</td>
                            <td><span class="badge bg-label-warning text-dark">Stable</span></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-6">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body">
                    <h6 class="fw-semibold mb-3">Recent Delivery Failures</h6>
                    <p class="text-muted mb-0">View email sends that require follow-up and retry.</p>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body">
                    <h6 class="fw-semibold mb-3">Optimization Suggestions</h6>
                    <p class="text-muted mb-0">Improve engagement with suggested template and timing changes.</p>
                </div>
            </div>
        </div>
    </div>

    <script src="{{ asset('assets/vendor/libs/apex-charts/apexcharts.js') }}"></script>
    <script>
        (function() {
            if (typeof window.ApexCharts === 'undefined') {
                return;
            }

            var chartElement = document.querySelector('#emailEngagementChart');
            if (!chartElement) {
                return;
            }

            var options = {
                chart: {
                    type: 'line',
                    height: 320,
                    toolbar: {
                        show: false
                    },
                    zoom: {
                        enabled: false
                    }
                },
                stroke: {
                    curve: 'smooth',
                    width: 3
                },
                markers: {
                    size: 4,
                    hover: {
                        sizeOffset: 2
                    }
                },
                series: [{
                        name: 'Sent',
                        data: [420, 530, 480, 620, 700, 650, 780, 860, 900, 920, 980, 1020]
                    },
                    {
                        name: 'Opened',
                        data: [310, 420, 390, 520, 600, 580, 680, 720, 770, 820, 860, 900]
                    },
                    {
                        name: 'Actions',
                        data: [180, 250, 220, 320, 360, 340, 410, 450, 480, 520, 560, 590]
                    }
                ],
                xaxis: {
                    categories: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov',
                        'Dec'
                    ],
                    labels: {
                        style: {
                            colors: '#64748b'
                        }
                    },
                    axisBorder: {
                        show: false
                    },
                    axisTicks: {
                        show: false
                    }
                },
                yaxis: {
                    labels: {
                        style: {
                            colors: '#64748b'
                        }
                    }
                },
                legend: {
                    show: false
                },
                colors: ['#0f172a', '#0b86ff', '#22c55e'],
                tooltip: {
                    theme: 'light'
                }
            };

            var chart = new ApexCharts(chartElement, options);
            chart.render();
        })();
    </script>

</div>
