<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Task extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'title',
        'description',
        'credentialing_case_id',
        'provider_id',
        'assigned_admin_id',
        'created_by_admin_id',
        'priority_id',
        'due_date',
        'completed_at',
        'task_type',
    ];

    protected $casts = [
        'due_date' => 'date',
        'completed_at' => 'datetime',
    ];

    public function credentialingCase(): BelongsTo
    {
        return $this->belongsTo(CredentialingCase::class);
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(ProviderDetails::class, 'provider_id');
    }

    public function assignedAdmin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'assigned_admin_id');
    }

    public function createdByAdmin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by_admin_id');
    }

    public function priority(): BelongsTo
    {
        return $this->belongsTo(Priority::class);
    }

    public function isCompleted(): bool
    {
        return $this->completed_at !== null;
    }

    public function kanbanColumn(): string
    {
        if ($this->isCompleted()) {
            return 'completed';
        }

        if (! $this->due_date) {
            return 'upcoming';
        }

        if ($this->due_date->isPast()) {
            return 'overdue';
        }

        if ($this->due_date->isToday()) {
            return 'due_today';
        }

        return 'upcoming';
    }

    public function scopeOpen($query)
    {
        return $query->whereNull('completed_at');
    }

    public function scopeForColumn($query, string $column)
    {
        return match ($column) {
            'completed' => $query->whereNotNull('completed_at'),
            'overdue' => $query->open()->whereDate('due_date', '<', now()),
            'due_today' => $query->open()->whereDate('due_date', now()),
            'upcoming' => $query->open()->where(function ($q) {
                $q->whereNull('due_date')->orWhereDate('due_date', '>', now());
            }),
            default => $query,
        };
    }

    public function markComplete(): void
    {
        $this->update(['completed_at' => now()]);
    }

    public function markIncomplete(): void
    {
        $this->update(['completed_at' => null]);
    }
}
