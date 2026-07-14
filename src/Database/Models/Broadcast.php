<?php

namespace Devflow\TelegramBot\Database\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $message
 * @property string $type  text|photo|document|video|audio|voice|animation|copy
 * @property string|null $media file_id for photo/document/video/audio/voice/animation types
 * @property array|null $options For type=copy: ['from_chat_id' => ..., 'message_id' => ...] plus any copyMessage options
 * @property int|null $notify_chat_id Chat to notify with a summary when the broadcast completes
 * @property string $status  pending|running|completed|failed
 * @property int $total_recipients
 * @property int $sent_count
 * @property int $failed_count
 * @property \Carbon\Carbon|null $scheduled_at
 * @property \Carbon\Carbon|null $started_at
 * @property \Carbon\Carbon|null $completed_at
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class Broadcast extends Model
{
    protected $table = 'telegram_broadcasts';

    protected $fillable = [
        'message',
        'type',
        'media',
        'options',
        'notify_chat_id',
        'status',
        'total_recipients',
        'sent_count',
        'failed_count',
        'scheduled_at',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'options' => 'array',
        'scheduled_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function isPending(): bool { return $this->status === 'pending'; }
    public function isRunning(): bool { return $this->status === 'running'; }
    public function isCompleted(): bool { return $this->status === 'completed'; }
    public function isFailed(): bool { return $this->status === 'failed'; }

    public function progressPercent(): float
    {
        if ($this->total_recipients === 0) return 0.0;
        return round(($this->sent_count + $this->failed_count) / $this->total_recipients * 100, 1);
    }
}
