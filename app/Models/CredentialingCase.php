<?php

namespace App\Models;

use App\Services\DelayOwnershipService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CredentialingCase extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'case_number',
        'provider_id',
        'practice_id',
        'location_id',
        'provider_practice_location_id',
        'payer_id',
        'case_type_id',
        'status_id',
        'delay_owner_id',
        'priority_id',
        'assigned_admin_id',
        'assigned_manager_id',
        'state',
        'intake_date',
        'submission_date',
        'acknowledgement_date',
        'payer_follow_up_date',
        'expected_completion_date',
        'approval_date',
        'effective_date',
        'payer_provider_id',
        'payer_group_id',
        'eft_status',
        'era_status',
        'billing_notified',
        'billing_notes',
        'ready_to_bill',
        'termination_date',
        'revalidation_due_date',
        'next_follow_up_date',
        'notes',
        'is_escalated',
        'do_not_automate',
        'last_action_at',
    ];

    protected $casts = [
        'intake_date' => 'date',
        'submission_date' => 'date',
        'payer_follow_up_date' => 'date',
        'expected_completion_date' => 'date',
        'effective_date' => 'date',
        'approval_date' => 'date',
        'acknowledgement_date' => 'date',
        'billing_notified' => 'boolean',
        'ready_to_bill' => 'boolean',
        'do_not_automate' => 'boolean',
        'termination_date' => 'date',
        'revalidation_due_date' => 'date',
        'next_follow_up_date' => 'date',
        'is_escalated' => 'boolean',
        'last_action_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (CredentialingCase $case) {
            if (empty($case->case_number)) {
                $case->case_number = self::generateCaseNumber();
            }
            if (empty($case->intake_date)) {
                $case->intake_date = now()->toDateString();
            }
            if (empty($case->last_action_at)) {
                $case->last_action_at = now();
            }
        });
    }

    public static function generateCaseNumber(): string
    {
        $year = now()->format('Y');
        $latest = self::withTrashed()
            ->where('case_number', 'like', "APP-{$year}-%")
            ->orderByDesc('id')
            ->value('case_number');

        $sequence = 1;
        if ($latest && preg_match('/APP-\d{4}-(\d+)/', $latest, $matches)) {
            $sequence = (int) $matches[1] + 1;
        }

        return sprintf('APP-%s-%04d', $year, $sequence);
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(ProviderDetails::class, 'provider_id');
    }

    public function practice(): BelongsTo
    {
        return $this->belongsTo(Practice::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function payer(): BelongsTo
    {
        return $this->belongsTo(Payer::class);
    }

    public function caseType(): BelongsTo
    {
        return $this->belongsTo(CaseType::class);
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(Status::class);
    }

    public function delayOwner(): BelongsTo
    {
        return $this->belongsTo(DelayOwner::class);
    }

    public function priority(): BelongsTo
    {
        return $this->belongsTo(Priority::class);
    }

    public function assignedAdmin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'assigned_admin_id');
    }

    public function assignedManager(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'assigned_manager_id');
    }

    public function providerPracticeLocation(): BelongsTo
    {
        return $this->belongsTo(ProviderPracticeLocation::class);
    }

    public function delayOwnerHistories(): HasMany
    {
        return $this->hasMany(DelayOwnerHistory::class);
    }

    public function packetGenerations(): HasMany
    {
        return $this->hasMany(PacketGeneration::class);
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(CaseStatusHistory::class)->latest();
    }

    public function activities(): HasMany
    {
        return $this->hasMany(CaseActivity::class)->latest();
    }

    public function documentItems(): HasMany
    {
        return $this->hasMany(CaseDocumentItem::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function emailMessages(): HasMany
    {
        return $this->hasMany(EmailMessage::class);
    }

    public function slaTimers(): HasMany
    {
        return $this->hasMany(CaseSlaTimer::class);
    }

    public function delayOverrides(): HasMany
    {
        return $this->hasMany(DelayOverride::class);
    }

    public function seedDocumentChecklist(): void
    {
        $requirements = PayerDocumentRequirement::where('payer_id', $this->payer_id)
            ->where(function ($q) {
                $q->whereNull('state');
                if ($this->state) {
                    $q->orWhere('state', $this->state);
                }
            })
            ->get();

        foreach ($requirements as $requirement) {
            CaseDocumentItem::firstOrCreate(
                [
                    'credentialing_case_id' => $this->id,
                    'document_type_id' => $requirement->document_type_id,
                ],
                [
                    'is_required' => $requirement->is_required,
                    'is_received' => false,
                ]
            );
        }
    }

    public function syncChecklistFromDocument(Document $document): void
    {
        if (! $document->document_type_id) {
            return;
        }

        $item = $this->documentItems()
            ->where('document_type_id', $document->document_type_id)
            ->first();

        if ($item) {
            $item->update([
                'document_id' => $document->id,
                'is_received' => true,
            ]);

            $item->load('documentType');
            app(\App\Services\TaskSyncService::class)->completeDocumentTask($this, $item);
        }
    }

    public function getChecklistCompletionAttribute(): array
    {
        $items = $this->documentItems;
        $total = $items->count();
        $received = $items->where('is_received', true)->count();

        return [
            'total' => $total,
            'received' => $received,
            'percent' => $total > 0 ? round(($received / $total) * 100) : 0,
        ];
    }

    public function getAgingDaysAttribute(): int
    {
        $start = $this->submission_date ?? $this->intake_date;

        return $start ? $start->diffInDays(now()) : 0;
    }

    public function isOverdue(): bool
    {
        if (! $this->next_follow_up_date) {
            return false;
        }

        return $this->next_follow_up_date->isPast();
    }

    public function scopeActive($query)
    {
        return $query->whereHas('status', function ($q) {
            $q->whereNotIn('dashboard_category', ['approved', 'closed']);
        });
    }

    public function scopeFilterCategory($query, ?string $category)
    {
        if (! $category) {
            return $query;
        }

        if ($category === 'overdue') {
            return $query->whereDate('next_follow_up_date', '<', now()->toDateString());
        }

        if ($category === 'escalated') {
            return $query->where('is_escalated', true);
        }

        return $query->whereHas('status', fn ($q) => $q->where('dashboard_category', $category));
    }

    public function recordStatusChange(int $statusId, ?int $adminId = null, ?string $notes = null): void
    {
        $status = Status::findOrFail($statusId);

        $this->update([
            'status_id' => $statusId,
            'delay_owner_id' => $status->delay_owner_id ?? $this->delay_owner_id,
            'last_action_at' => now(),
        ]);

        CaseStatusHistory::create([
            'credentialing_case_id' => $this->id,
            'status_id' => $statusId,
            'delay_owner_id' => $status->delay_owner_id,
            'changed_by_admin_id' => $adminId,
            'notes' => $notes,
        ]);

        CaseActivity::create([
            'credentialing_case_id' => $this->id,
            'activity_type' => 'status_change',
            'admin_id' => $adminId,
            'summary' => 'Status changed to ' . $status->name . ($notes ? ": {$notes}" : ''),
        ]);

        app(DelayOwnershipService::class)->applyOnStatusChange($this->fresh(), $status, $adminId);
    }

    public function addActivity(string $type, string $summary, ?int $adminId = null, ?string $reference = null, ?string $nextActionDate = null, ?int $userId = null): void
    {
        CaseActivity::create([
            'credentialing_case_id' => $this->id,
            'activity_type' => $type,
            'admin_id' => $adminId,
            'user_id' => $userId,
            'summary' => $summary,
            'reference_number' => $reference,
            'next_action_date' => $nextActionDate,
        ]);

        $updates = ['last_action_at' => now()];
        if ($nextActionDate) {
            $updates['next_follow_up_date'] = $nextActionDate;
        }
        $this->update($updates);
    }
}
