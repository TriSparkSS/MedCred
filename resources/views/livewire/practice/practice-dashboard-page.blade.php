<div>
    <div class="container-fluid flex-grow-1 px-3 px-md-4 py-3 py-md-4">
        <x-practice.page-header
            title="Welcome, {{ $practice->legal_name }}"
            subtitle="Monitor credentialing applications for your practice and upload requested documents."
        >
            <x-slot:actions>
                @if(can_do('portal.documents.upload'))
                <a href="{{ route('practice.documents') }}" class="btn btn-primary">
                    <i class="ti tabler-upload me-1"></i> Upload Center
                </a>
                @endif
                @if(can_do('portal.cases.view'))
                <a href="{{ route('practice.cases') }}" class="btn btn-outline-secondary">
                    <i class="ti tabler-briefcase me-1"></i> Applications
                </a>
                @endif
            </x-slot:actions>
        </x-practice.page-header>

        <div class="row g-3 mb-4">
            <div class="col-sm-6 col-xl">
                <x-practice.stat-card label="Active Applications" :value="$stats['active_cases']" hint="Currently in progress" />
            </div>
            <div class="col-sm-6 col-xl">
                @if(can_do('portal.action_items.view'))
                <a href="{{ route('practice.action-items') }}" class="text-decoration-none">
                    <x-practice.stat-card label="Action Needed" :value="$stats['pending_action']" valueClass="text-warning" hint="Awaiting documents" />
                </a>
                @else
                <x-practice.stat-card label="Action Needed" :value="$stats['pending_action']" valueClass="text-warning" hint="Awaiting documents" />
                @endif
            </div>
            <div class="col-sm-6 col-xl">
                <x-practice.stat-card label="Approved" :value="$stats['approved']" valueClass="text-success" hint="Completed enrollments" />
            </div>
            <div class="col-sm-6 col-xl">
                <x-practice.stat-card label="Linked Providers" :value="$stats['linked_providers']" hint="Providers at this practice" />
            </div>
            <div class="col-sm-6 col-xl">
                <x-practice.stat-card label="Checklist Pending" :value="$stats['checklist_pending']" hint="Required items outstanding" />
            </div>
        </div>

        <x-portal.assigned-items-table
            :items="$myItems"
            :filter="$assignmentFilter"
            :counts="$assignmentCounts"
        />
    </div>
</div>
