<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FreeCertQuota extends BaseModel
{
    const string|null UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'type',
        'order_id',
        'quota',
        'quota_before',
        'quota_after',
    ];

    protected $casts = [
        'quota' => 'integer',
        'quota_before' => 'integer',
        'quota_after' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withoutGlobalScopes();
    }
}
