<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EmailMessage extends Model
{
    protected $fillable = [
        'credentialing_case_id',
        'notification_template_id',
        'sent_by_admin_id',
        'direction',
        'thread_id',
        'external_message_id',
        'from_address',
        'to_address',
        'cc_address',
        'subject',
        'body',
        'status',
        'sent_at',
        'received_at',
        'error_message',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
        'received_at' => 'datetime',
    ];

    public function credentialingCase(): BelongsTo
    {
        return $this->belongsTo(CredentialingCase::class);
    }

    public function notificationTemplate(): BelongsTo
    {
        return $this->belongsTo(NotificationTemplate::class);
    }

    public function sentByAdmin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'sent_by_admin_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(EmailAttachment::class);
    }

    public function scopeOutbound($query)
    {
        return $query->where('direction', 'outbound');
    }

    public function scopeInbound($query)
    {
        return $query->where('direction', 'inbound');
    }

    public function scopeUnlinked($query)
    {
        return $query->whereNull('credentialing_case_id');
    }

    public function isLinked(): bool
    {
        return $this->credentialing_case_id !== null;
    }
}
