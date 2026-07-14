<?php

namespace Devflow\TelegramBot\Database\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $telegram_id
 * @property int $chat_id
 * @property string $first_name
 * @property string|null $last_name
 * @property string|null $username
 * @property string|null $language_code
 * @property string|null $language Explicit user-chosen locale (see Support\Lang) — takes priority over language_code.
 * @property string $role
 * @property array|null $permissions
 * @property bool $is_banned
 * @property string|null $ban_reason
 * @property \Carbon\Carbon|null $banned_at
 * @property bool $is_active
 * @property string|null $step
 * @property array|null $temp_data
 * @property string|null $referral_code
 * @property int|null $invited_by
 * @property \Carbon\Carbon $joined_at
 * @property \Carbon\Carbon $last_activity_at
 * @property array|null $rate_hits
 * @property string $current_panel
 */
class TelegramUser extends Model
{
    protected $table = 'telegram_users';

    public $timestamps = false;

    // Guard only the primary key — any other column (including custom ones
    // added by your own migrations) is mass-assignable via create()/update().
    // Extend this class (and point the `user_model` config key at your
    // subclass) if you want a stricter allowlist for your own project.
    protected $guarded = ['id'];

    protected $casts = [
        'permissions' => 'array',
        'temp_data' => 'array',
        'rate_hits' => 'array',
        'is_banned' => 'boolean',
        'is_active' => 'boolean',
        'banned_at' => 'datetime',
        'joined_at' => 'datetime',
        'last_activity_at' => 'datetime',
    ];

    public function inviter(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(self::class, 'invited_by');
    }

    public function referrals(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(self::class, 'invited_by');
    }

    public function isAdmin(): bool
    {
        return in_array($this->role, ['admin', 'superadmin'], true);
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === 'superadmin';
    }

    public function hasPermission(string $permission): bool
    {
        if ($this->isSuperAdmin()) return true;
        return (bool) ($this->permissions[$permission] ?? false);
    }

    public function ban(string $reason = ''): void
    {
        $this->is_banned = true;
        $this->ban_reason = $reason ?: null;
        $this->banned_at = \Carbon\Carbon::now();
        $this->save();
    }

    public function unban(): void
    {
        $this->is_banned = false;
        $this->ban_reason = null;
        $this->banned_at = null;
        $this->save();
    }

    public function touchActivity(): void
    {
        $this->last_activity_at = \Carbon\Carbon::now();
        $this->save();
    }
}
