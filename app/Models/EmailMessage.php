<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EmailMessage extends Model
{
    protected $fillable = [
        'credentialing_case_id',
        'provider_id',
        'notification_template_id',
        'sent_by_admin_id',
        'direction',
        'thread_id',
        'external_message_id',
        'message_id',
        'in_reply_to',
        'references',
        'imap_uid',
        'from_address',
        'to_address',
        'cc_address',
        'subject',
        'body',
        'status',
        'queue_category',
        'is_unlinked',
        'has_pending_attachments',
        'sent_at',
        'received_at',
        'error_message',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
        'received_at' => 'datetime',
        'is_unlinked' => 'boolean',
        'has_pending_attachments' => 'boolean',
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
        return $query->where(function ($q) {
            $q->whereNull('credentialing_case_id')->orWhere('is_unlinked', true);
        });
    }

    public function scopeForQueue($query, string $queue)
    {
        return match ($queue) {
            'inbox' => $query->inbound()->whereIn('queue_category', ['inbox', 'provider_responses', 'payer_responses']),
            'sent' => $query->outbound()->where('status', 'sent'),
            'unlinked' => $query->unlinked(),
            'provider_responses' => $query->where('queue_category', 'provider_responses'),
            'payer_responses' => $query->where('queue_category', 'payer_responses'),
            'attachments_pending' => $query->where('has_pending_attachments', true),
            'replies_awaited' => $query->where('queue_category', 'replies_awaited'),
            'escalation' => $query->where('queue_category', 'escalation'),
            'failed' => $query->whereIn('status', ['failed', 'bounced']),
            default => $query,
        };
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(ProviderDetails::class, 'provider_id');
    }

    public function isLinked(): bool
    {
        return $this->credentialing_case_id !== null;
    }
}
