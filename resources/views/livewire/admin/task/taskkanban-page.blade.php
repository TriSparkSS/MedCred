<div class="container-fluid flex-grow-1 px-3 px-md-4 py-3 py-md-4">

    <div class="row g-4 mb-3 align-items-center">
        <div class="col-md-8">
            <h2 class="fw-bold mb-1">Credentialing Pipeline</h2>
            <p class="text-muted mb-0">Manage and track provider task statuses.</p>
        </div>
        <div class="col-md-4 text-md-end">
            <div class="btn-group btn-group-sm shadow-sm" role="group" aria-label="View toggle">
                <button type="button" class="btn btn-outline-secondary active">Kanban</button>
                <button type="button" class="btn btn-outline-secondary">List</button>
            </div>
            <button class="btn btn-primary btn-sm ms-2">
                <i class="ti tabler-plus me-1"></i>
                New Task
            </button>
        </div>
    </div>

    <div class="row g-2 align-items-center mb-4">
        <div class="col-auto">
            <button class="btn btn-secondary btn-sm px-3">
                <i class="ti tabler-checks me-1"></i>
                My Tasks
            </button>
        </div>
        <div class="col-auto">
            <button class="btn btn-outline-secondary btn-sm px-3">
                <i class="ti tabler-alert-circle me-1"></i>
                High Priority
            </button>
        </div>
        <div class="col-auto">
            <button class="btn btn-outline-secondary btn-sm px-3">
                <i class="ti tabler-link me-1"></i>
                Linked Provider
            </button>
        </div>
        <div class="col text-end text-muted">Showing 14 active tasks</div>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-body p-3">
            <div id="myKanban" class="d-flex overflow-auto"></div>
        </div>
    </div>

    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/jkanban/jkanban.css') }}">
    <script src="{{ asset('assets/vendor/libs/jkanban/jkanban.js') }}"></script>

    <style>
        .kanban-board {
            min-width: 320px;
            box-shadow: 0 1px 2px rgba(15, 23, 42, 0.08);
            border-radius: 16px;
            border: 1px solid rgba(148, 163, 184, 0.16);
        }

        .kanban-board-title .kanban-title-board {
            font-size: 0.95rem;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 0.5rem;
        }

        .kanban-board-title .board-count {
            font-size: 0.85rem;
            color: #64748b;
        }

        .kanban-item {
            background: #fff;
            border-radius: 14px;
            border: 1px solid rgba(148, 163, 184, 0.16);
            margin-bottom: 1rem;
            box-shadow: 0 1px 2px rgba(15, 23, 42, 0.06);
            padding: 1rem;
        }

        .task-card-title {
            font-weight: 700;
            margin-bottom: 0.5rem;
            color: #0f172a;
        }

        .task-card-meta {
            font-size: 0.85rem;
            color: #64748b;
            margin-bottom: 0.75rem;
        }

        .task-card-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 0.5rem;
        }

        .task-label {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            font-size: 0.7rem;
            font-weight: 700;
            letter-spacing: 0.02em;
            text-transform: uppercase;
            padding: 0.35rem 0.6rem;
            border-radius: 999px;
        }

        .task-label.high {
            color: #b91c1c;
            background: rgba(244, 63, 94, 0.12);
        }

        .task-label.medium {
            color: #cd9b1c;
            background: rgba(251, 191, 36, 0.12);
        }

        .task-label.low {
            color: #0f766e;
            background: rgba(20, 184, 166, 0.12);
        }

        .task-chip {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            font-size: 0.8rem;
            color: #475569;
        }

        .task-chip .avatar {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 0.75rem;
            color: #fff;
            background: #2563eb;
        }

        .kanban-item .task-badge {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            display: inline-block;
        }
    </style>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var boards = [{
                    id: 'overdue',
                    title: '<div class="kanban-board-title"><div class="d-flex align-items-center justify-content-between"><span>Overdue</span><span class="badge bg-danger bg-opacity-10 text-danger">3</span></div><div class="board-count">High urgency tasks</div></div>',
                    class: 'bg-light',
                    dragTo: ['due_today', 'upcoming'],
                    item: [{
                            id: 'task1',
                            title: '<div class="task-card"><div class="d-flex justify-content-between align-items-start mb-2"><span class="task-label high">High</span><span class="task-badge bg-danger"></span></div><div class="task-card-title">Follow-up with Aetna</div><div class="task-card-meta">Dr. Robert Wright | Aetna</div><div class="task-card-footer"><div class="task-chip"><span class="avatar">RW</span>Oct 12, 2023</div><i class="ti tabler-dots"></i></div></div>'
                        },
                        {
                            id: 'task2',
                            title: '<div class="task-card"><div class="d-flex justify-content-between align-items-start mb-2"><span class="task-label high">High</span><span class="task-badge bg-danger"></span></div><div class="task-card-title">Upload NJ License</div><div class="task-card-meta">Dr. Emily Chen | UnitedHealth</div><div class="task-card-footer"><div class="task-chip"><span class="avatar">EC</span>Oct 15, 2023</div><i class="ti tabler-dots"></i></div></div>'
                        },
                        {
                            id: 'task10',
                            title: '<div class="task-card"><div class="d-flex justify-content-between align-items-start mb-2"><span class="task-label high">High</span><span class="task-badge bg-danger"></span></div><div class="task-card-title">Confirm contract terms</div><div class="task-card-meta">Dr. Allison Moore | Humana</div><div class="task-card-footer"><div class="task-chip"><span class="avatar">AM</span>Oct 17, 2023</div><i class="ti tabler-dots"></i></div></div>'
                        }
                    ]
                },
                {
                    id: 'due_today',
                    title: '<div class="kanban-board-title"><div class="d-flex align-items-center justify-content-between"><span>Due Today</span><span class="badge bg-info bg-opacity-10 text-info">2</span></div><div class="board-count">Today&apos;s priorities</div></div>',
                    class: 'bg-light',
                    dragTo: ['overdue', 'upcoming'],
                    item: [{
                            id: 'task3',
                            title: '<div class="task-card"><div class="d-flex justify-content-between align-items-start mb-2"><span class="task-label medium">Medium</span><span class="task-badge bg-info"></span></div><div class="task-card-title">CAQH Re-attestation</div><div class="task-card-meta">Dr. James Wilson | Medicare</div><div class="task-card-footer"><div class="task-chip"><span class="avatar">JW</span>5:00 PM Today</div><i class="ti tabler-dots"></i></div></div>'
                        },
                        {
                            id: 'task6',
                            title: '<div class="task-card"><div class="d-flex justify-content-between align-items-start mb-2"><span class="task-label medium">Medium</span><span class="task-badge bg-info"></span></div><div class="task-card-title">Submit credential packet</div><div class="task-card-meta">Dr. Olivia Parker | Anthem</div><div class="task-card-footer"><div class="task-chip"><span class="avatar">OP</span>Today</div><i class="ti tabler-dots"></i></div></div>'
                        }
                    ]
                },
                {
                    id: 'upcoming',
                    title: '<div class="kanban-board-title"><div class="d-flex align-items-center justify-content-between"><span>Upcoming</span><span class="badge bg-secondary bg-opacity-10 text-secondary">5</span></div><div class="board-count">Planned work ahead</div></div>',
                    class: 'bg-light',
                    dragTo: ['overdue', 'due_today'],
                    item: [{
                            id: 'task4',
                            title: '<div class="task-card"><div class="d-flex justify-content-between align-items-start mb-2"><span class="task-label low">Low</span><span class="task-badge bg-success"></span></div><div class="task-card-title">Verify DEA Registration</div><div class="task-card-meta">Dr. Robert Wright | BCBS</div><div class="task-card-footer"><div class="task-chip"><span class="avatar">RW</span>Oct 28</div><i class="ti tabler-dots"></i></div></div>'
                        },
                        {
                            id: 'task5',
                            title: '<div class="task-card"><div class="d-flex justify-content-between align-items-start mb-2"><span class="task-label medium">Medium</span><span class="task-badge bg-success"></span></div><div class="task-card-title">Contract Renewal Signature</div><div class="task-card-meta">Dr. Sarah Lopez | Cigna</div><div class="task-card-footer"><div class="task-chip"><span class="avatar">SL</span>Oct 30</div><i class="ti tabler-dots"></i></div></div>'
                        },
                        {
                            id: 'task7',
                            title: '<div class="task-card"><div class="d-flex justify-content-between align-items-start mb-2"><span class="task-label low">Low</span><span class="task-badge bg-success"></span></div><div class="task-card-title">Review Medicaid enrollment</div><div class="task-card-meta">Dr. Paul Benson | Medicaid</div><div class="task-card-footer"><div class="task-chip"><span class="avatar">PB</span>Nov 02</div><i class="ti tabler-dots"></i></div></div>'
                        },
                        {
                            id: 'task8',
                            title: '<div class="task-card"><div class="d-flex justify-content-between align-items-start mb-2"><span class="task-label low">Low</span><span class="task-badge bg-success"></span></div><div class="task-card-title">Update provider bio</div><div class="task-card-meta">Dr. Leslie Kim | BCBS</div><div class="task-card-footer"><div class="task-chip"><span class="avatar">LK</span>Nov 05</div><i class="ti tabler-dots"></i></div></div>'
                        },
                        {
                            id: 'task9',
                            title: '<div class="task-card"><div class="d-flex justify-content-between align-items-start mb-2"><span class="task-label low">Low</span><span class="task-badge bg-success"></span></div><div class="task-card-title">Schedule background check</div><div class="task-card-meta">Dr. Maya Patel | Aetna</div><div class="task-card-footer"><div class="task-chip"><span class="avatar">MP</span>Nov 08</div><i class="ti tabler-dots"></i></div></div>'
                        }
                    ]
                }
            ];

            new jKanban({
                element: '#myKanban',
                gutter: '24px',
                widthBoard: '320px',
                boards: boards,
                dragBoards: false,
                buttonContent: '+',
                itemHandleOptions: {
                    enabled: false
                }
            });
        });
    </script>

</div>
