<div>
    <div class="container-fluid flex-grow-1 px-3 px-md-4 py-3 py-md-4">
        <x-provider.page-header
            title="Welcome, {{ $provider->user->name }}"
            subtitle="Track your credentialing applications and upload requested documents."
        >
            <x-slot:actions>
                @if(can_do('portal.documents.view'))
                <a href="{{ route('provider.documents') }}" class="btn btn-primary">
                    <i class="ti tabler-upload me-1"></i> Upload Center
                </a>
                @endif
                @if(can_do('portal.cases.view'))
                <a href="{{ route('provider.cases') }}" class="btn btn-outline-secondary">
                    <i class="ti tabler-briefcase me-1"></i> My Applications
                </a>
                @endif
            </x-slot:actions>
        </x-provider.page-header>

        <div class="row g-3 mb-4">
            <div class="col-sm-6 col-xl">
                <x-provider.stat-card label="Active Applications" :value="$stats['active_cases']" hint="Currently in progress" />
            </div>
            <div class="col-sm-6 col-xl">
                @if(can_do('portal.action_items.view'))
                <a href="{{ route('provider.action-items') }}" class="text-decoration-none">
                    <x-provider.stat-card label="Action Needed" :value="$stats['pending_action']" valueClass="text-warning" hint="Awaiting your documents" />
                </a>
                @else
                <x-provider.stat-card label="Action Needed" :value="$stats['pending_action']" valueClass="text-warning" hint="Awaiting your documents" />
                @endif
            </div>
            <div class="col-sm-6 col-xl">
                <x-provider.stat-card label="Approved" :value="$stats['approved']" valueClass="text-success" hint="Completed enrollments" />
            </div>
            <div class="col-sm-6 col-xl">
                <x-provider.stat-card label="Docs Expiring" :value="$stats['documents_expiring']" hint="Within 30 days" />
            </div>
            <div class="col-sm-6 col-xl">
                <x-provider.stat-card label="Checklist Pending" :value="$stats['checklist_pending']" hint="Required items outstanding" />
            </div>
        </div>

        <x-portal.assigned-items-table
            :items="$myItems"
            :filter="$assignmentFilter"
            :counts="$assignmentCounts"
        />
    </div>
</div>
