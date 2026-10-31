<?php

namespace App\Models;

use App\Services\DocumentService;
use App\Services\FileMalwareScanner;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

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
        'document_number',
        'effective_date',
        'expiry_date',
        'state',
        'status',
        'visibility',
        'access_level',
        'allowed_roles',
        'verification_status',
        'rejection_reason',
        'verified_by_admin_id',
        'verified_at',
        'uploaded_by_admin_id',
        'uploaded_by_user_id',
    ];

    protected $casts = [
        'effective_date' => 'date',
        'expiry_date' => 'date',
        'verified_at' => 'datetime',
        'allowed_roles' => 'array',
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

    public function verifiedByAdmin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'verified_by_admin_id');
    }

    public function uploadedByAdmin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'uploaded_by_admin_id');
    }

    public function uploadedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(DocumentVersion::class)->orderByDesc('version_number');
    }

    public function currentVersion(): HasOne
    {
        return $this->hasOne(DocumentVersion::class)->where('is_current', true);
    }

    public function getCurrentVersionAttribute(): ?DocumentVersion
    {
        return $this->getRelationValue('currentVersion');
    }

    public function getAccessLevelAttribute(): ?string
    {
        return $this->attributes['access_level'] ?? $this->attributes['visibility'] ?? null;
    }

    public function setAccessLevelAttribute(?string $value): void
    {
        $this->attributes['visibility'] = $value;
    }

    public function isExpiringSoon(int $days = 30): bool
    {
        if (! $this->expiry_date) {
            return false;
        }

        return ! $this->isExpired()
            && $this->expiry_date->lte(now()->addDays($days)->endOfDay());
    }

    public function isExpired(): bool
    {
        return (bool) ($this->expiry_date && $this->expiry_date->lt(now()->startOfDay()));
    }

    public function scopeExpiringSoon($query, int $days = 30)
    {
        return $query->whereNotNull('expiry_date')
            ->whereDate('expiry_date', '>=', now()->toDateString())
            ->whereDate('expiry_date', '<=', now()->addDays($days)->toDateString());
    }

    public function scopeExpired($query)
    {
        return $query->whereNotNull('expiry_date')->whereDate('expiry_date', '<', now()->toDateString());
    }

    public function addVersion($file, ?int $adminId = null, ?string $notes = null, ?int $userId = null): DocumentVersion
    {
        app(FileMalwareScanner::class)->assertClean($file, $adminId);

        $shouldEncrypt = app(DocumentService::class)->encryptionEnabled()
            || $this->versions()->where('is_encrypted', true)->exists();

        if ($shouldEncrypt) {
            $extension = $file->getClientOriginalExtension();
            $filename = Str::uuid()->toString().($extension ? '.'.$extension : '').'.enc';
            $path = "documents/{$this->id}/{$filename}";
            $rawContents = file_get_contents($file->getRealPath());
            Storage::disk('public')->put($path, Crypt::encryptString($rawContents !== false ? $rawContents : ''));
        } else {
            $path = $file->store("documents/{$this->id}", 'public');
        }

        return DB::transaction(function () use ($file, $path, $adminId, $notes, $userId, $shouldEncrypt) {
            self::whereKey($this->id)->lockForUpdate()->first();

            $this->versions()->where('is_current', true)->update(['is_current' => false]);

            $nextVersion = ($this->versions()->lockForUpdate()->max('version_number') ?? 0) + 1;

            return $this->versions()->create([
                'version_number' => $nextVersion,
                'file_path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'file_size' => $file->getSize(),
                'mime_type' => $file->getMimeType(),
                'is_encrypted' => $shouldEncrypt,
                'uploaded_by_admin_id' => $adminId,
                'uploaded_by_user_id' => $userId,
                'notes' => $notes,
                'is_current' => true,
            ]);
        });
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
