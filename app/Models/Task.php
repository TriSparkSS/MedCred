<?php

namespace App\Models;

use App\Enums\TaskStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Task extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'title',
        'description',
        'credentialing_case_id',
        'provider_id',
        'payer_id',
        'assigned_admin_id',
        'assigned_by_admin_id',
        'created_by_admin_id',
        'priority_id',
        'due_date',
        'follow_up_date',
        'completed_at',
        'task_type',
        'automation_key',
        'status',
        'is_escalated',
        'escalated_at',
        'escalated_by_admin_id',
    ];

    protected $casts = [
        'due_date' => 'date',
        'follow_up_date' => 'date',
        'completed_at' => 'datetime',
        'escalated_at' => 'datetime',
        'is_escalated' => 'boolean',
        'status' => TaskStatus::class,
    ];

    public function credentialingCase(): BelongsTo
    {
        return $this->belongsTo(CredentialingCase::class);
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(ProviderDetails::class, 'provider_id');
    }

    public function payer(): BelongsTo
    {
        return $this->belongsTo(Payer::class);
    }

    public function assignedAdmin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'assigned_admin_id');
    }

    public function assignedByAdmin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'assigned_by_admin_id');
    }

    public function createdByAdmin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by_admin_id');
    }

    public function escalatedByAdmin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'escalated_by_admin_id');
    }

    public function priority(): BelongsTo
    {
        return $this->belongsTo(Priority::class);
    }

    public function notes(): HasMany
    {
        return $this->hasMany(TaskNote::class)->latest();
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(TaskAttachment::class)->latest();
    }

    public function activities(): HasMany
    {
        return $this->hasMany(TaskActivity::class)->latest();
    }

    public function isCompleted(): bool
    {
        return $this->status === TaskStatus::Completed || $this->completed_at !== null;
    }

    public function isOpen(): bool
    {
        return ! $this->isCompleted() && $this->status !== TaskStatus::Cancelled;
    }

    public function kanbanColumn(): string
    {
        if ($this->isCompleted()) {
            return 'completed';
        }

        if ($this->is_escalated || $this->status === TaskStatus::Escalated) {
            return 'escalated';
        }

        if (! $this->due_date) {
            return 'upcoming';
        }

        if ($this->due_date->isPast() && ! $this->due_date->isToday()) {
            return 'overdue';
        }

        if ($this->due_date->isToday()) {
            return 'due_today';
        }

        return 'upcoming';
    }

    public function scopeOpen($query)
    {
        return $query->whereNull('completed_at')
            ->whereNotIn('status', [TaskStatus::Completed->value, TaskStatus::Cancelled->value]);
    }

    public function scopeOverdue($query)
    {
        return $query->open()->whereDate('due_date', '<', now());
    }

    public function scopeDueToday($query)
    {
        return $query->open()->whereDate('due_date', now());
    }

    public function scopeUpcoming($query)
    {
        return $query->open()->where(function ($q) {
            $q->whereNull('due_date')->orWhereDate('due_date', '>', now());
        });
    }

    public function scopeEscalated($query)
    {
        return $query->open()->where(function ($q) {
            $q->where('is_escalated', true)
                ->orWhere('status', TaskStatus::Escalated->value)
                ->orWhere('task_type', 'escalation')
                ->orWhereHas('credentialingCase', fn ($q) => $q->where('is_escalated', true));
        });
    }

    public function scopeForAdmin($query, int $adminId)
    {
        return $query->where('assigned_admin_id', $adminId);
    }

    public function scopeForColumn($query, string $column)
    {
        return match ($column) {
            'completed' => $query->where(function ($q) {
                $q->whereNotNull('completed_at')
                    ->orWhere('status', TaskStatus::Completed->value);
            }),
            'escalated' => $query->escalated(),
            'overdue' => $query->overdue(),
            'due_today' => $query->dueToday(),
            'upcoming' => $query->upcoming(),
            default => $query,
        };
    }

    public function markComplete(): void
    {
        $this->update([
            'completed_at' => now(),
            'status' => TaskStatus::Completed,
        ]);
    }

    public function markIncomplete(): void
    {
        $this->update([
            'completed_at' => null,
            'status' => TaskStatus::Open,
        ]);
    }
}
