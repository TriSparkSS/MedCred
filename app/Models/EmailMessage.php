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
            'inbox' => $query->inbound(),
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

    /**
     * Plain-text body safe for display (decodes &nbsp; and other HTML entities).
     */
    public function displayBody(): string
    {
        $body = (string) ($this->body ?? '');

        if ($body === '') {
            return '';
        }

        for ($i = 0; $i < 3; $i++) {
            $decoded = html_entity_decode($body, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            if ($decoded === $body) {
                break;
            }
            $body = $decoded;
        }

        if (str_contains($body, '<') && preg_match('/<\/?[a-z][\s\S]*>/i', $body)) {
            $body = strip_tags($body);
        }

        $body = str_replace(["\xc2\xa0", "\xA0"], ' ', $body);
        $body = str_replace(["\r\n", "\r"], "\n", $body);
        $body = preg_replace('/[ \t]+/', ' ', $body) ?? $body;
        $body = preg_replace('/[ \t]+\n/', "\n", $body) ?? $body;
        $body = preg_replace("/\n{3,}/", "\n\n", $body) ?? $body;

        // Turn dense single-line emails into readable paragraphs
        $newlineCount = substr_count($body, "\n");
        if ($newlineCount < 2 && strlen($body) > 280) {
            $body = preg_replace('/\.\s+(?=[A-Z])/', ".\n\n", $body) ?? $body;
        }

        return trim($body);
    }

    public function fromDisplayName(): string
    {
        $from = (string) $this->from_address;
        $local = strstr($from, '@', true) ?: $from;
        $local = str_replace(['.', '_', '-', '+'], ' ', $local);

        return \Illuminate\Support\Str::title(trim($local)) ?: $from;
    }

    public function fromInitials(): string
    {
        $from = (string) $this->from_address;
        $local = strstr($from, '@', true) ?: $from;
        $parts = preg_split('/[._\-+]+/', $local) ?: [];
        $parts = array_values(array_filter($parts));

        if (count($parts) >= 2) {
            return strtoupper(substr($parts[0], 0, 1).substr($parts[1], 0, 1));
        }

        return strtoupper(substr($local, 0, 2)) ?: '?';
    }
}
