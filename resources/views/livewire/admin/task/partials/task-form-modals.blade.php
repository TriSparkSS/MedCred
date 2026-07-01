@if ($showCreateModal)
    <div class="modal fade show d-block" tabindex="-1" style="background: rgba(0,0,0,.5);">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">New Task</h5>
                    <button type="button" class="btn-close" wire:click="$set('showCreateModal', false)"></button>
                </div>
                <form wire:submit.prevent="saveTask">
                    @include('livewire.admin.task.partials.task-form-fields')
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" wire:click="$set('showCreateModal', false)">Cancel</button>
                        <button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:target="saveTask">
                            <span wire:loading.remove wire:target="saveTask">Create Task</span>
                            <span wire:loading wire:target="saveTask">Creating...</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endif

@if ($showEditModal)
    <div class="modal fade show d-block" tabindex="-1" style="background: rgba(0,0,0,.5);">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Task</h5>
                    <button type="button" class="btn-close" wire:click="$set('showEditModal', false)"></button>
                </div>
                <form wire:submit.prevent="updateTask">
                    @include('livewire.admin.task.partials.task-form-fields')
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" wire:click="$set('showEditModal', false)">Cancel</button>
                        <button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:target="updateTask">
                            <span wire:loading.remove wire:target="updateTask">Save Changes</span>
                            <span wire:loading wire:target="updateTask">Saving...</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endif
