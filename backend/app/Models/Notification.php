<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Notification extends BaseModel
{
    protected $fillable = [
        'notifiable_type',
        'notifiable_id',
        'type',
        'template',
        'data',
        'read_at',
        'sent_at',
        'status',
    ];

    protected $casts = [
        'data' => 'json',
        'read_at' => 'datetime',
        'sent_at' => 'datetime',
        'status' => 'integer',
    ];

    /**
     * 通知接收者
     */
    public function notifiable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * 通知模板
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(NotificationTemplate::class, 'template_id');
    }

    /**
     * 标记为已读
     */
    public function markAsRead(): void
    {
        if (! $this->read_at) {
            $this->update(['read_at' => now()]);
        }
    }

    /**
     * 标记为已发送
     */
    public function markAsSent(): void
    {
        $this->update([
            'status' => 'sent',
            'sent_at' => now(),
        ]);
    }

    /**
     * 标记为发送失败
     */
    public function markAsFailed(): void
    {
        $this->update(['status' => 'failed']);
    }
}
