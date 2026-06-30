<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Document extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'document_type_id',
        'provider_id',
        'practice_id',
        'credentialing_case_id',
        'payer_id',
        'title',
        'effective_date',
        'expiry_date',
        'state',
        'status',
        'uploaded_by_admin_id',
        'uploaded_by_user_id',
    ];

    protected $casts = [
        'effective_date' => 'date',
        'expiry_date' => 'date',
    ];

    public function documentType(): BelongsTo
    {
        return $this->belongsTo(DocumentType::class);
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(ProviderDetails::class, 'provider_id');
    }

    public function practice(): BelongsTo
    {
        return $this->belongsTo(Practice::class);
    }

    public function credentialingCase(): BelongsTo
    {
        return $this->belongsTo(CredentialingCase::class);
    }

    public function payer(): BelongsTo
    {
        return $this->belongsTo(Payer::class);
    }

    public function uploadedByAdmin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'uploaded_by_admin_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(DocumentVersion::class)->orderByDesc('version_number');
    }

    public function getCurrentVersionAttribute(): ?DocumentVersion
    {
        return $this->versions()->where('is_current', true)->first();
    }

    public function isExpiringSoon(int $days = 30): bool
    {
        if (! $this->expiry_date) {
            return false;
        }

        return $this->expiry_date->isFuture()
            && $this->expiry_date->lte(now()->addDays($days));
    }

    public function isExpired(): bool
    {
        return $this->expiry_date && $this->expiry_date->isPast();
    }

    public function scopeExpiringSoon($query, int $days = 30)
    {
        return $query->whereNotNull('expiry_date')
            ->whereDate('expiry_date', '>', now())
            ->whereDate('expiry_date', '<=', now()->addDays($days));
    }

    public function scopeExpired($query)
    {
        return $query->whereNotNull('expiry_date')->whereDate('expiry_date', '<', now());
    }

    public function addVersion($file, ?int $adminId = null, ?string $notes = null, ?int $userId = null): DocumentVersion
    {
        $this->versions()->where('is_current', true)->update(['is_current' => false]);

        $nextVersion = ($this->versions()->max('version_number') ?? 0) + 1;
        $path = $file->store("documents/{$this->id}", 'public');

        return $this->versions()->create([
            'version_number' => $nextVersion,
            'file_path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'file_size' => $file->getSize(),
            'mime_type' => $file->getMimeType(),
            'uploaded_by_admin_id' => $adminId,
            'uploaded_by_user_id' => $userId,
            'notes' => $notes,
            'is_current' => true,
        ]);
    }

    public static function createWithFile(array $data, $file, ?int $adminId = null, ?int $userId = null): self
    {
        $document = self::create([
            ...$data,
            'uploaded_by_admin_id' => $adminId,
            'uploaded_by_user_id' => $userId,
            'status' => 'active',
        ]);

        $document->addVersion($file, $adminId, 'Initial upload', $userId);

        return $document->fresh(['versions', 'documentType']);
    }
}
