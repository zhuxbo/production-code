<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

class NotificationTemplate extends BaseModel
{
    protected $fillable = [
        'name',
        'type',
        'content',
        'variables',
        'example',
        'status',
    ];

    protected $casts = [
        'variables' => 'json',
        'status' => 'integer',
    ];

    /**
     * 通知
     */
    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class, 'template_id');
    }

    /**
     * 渲染模板内容
     */
    public function render(array $data = []): string
    {
        $content = $this->content;
        foreach ($data as $key => $value) {
            $content = str_replace('{'.$key.'}', $value, $content);
        }

        return $content;
    }
}
