<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use Carbon\Carbon;

class AgentKey extends Model
{
    use HasFactory;

    protected $fillable = [
        'agent_key',
        'name',
        'agent_host',
        'status',
        'user_id',
        'expires_at'
    ];

    protected $casts = [
        'status' => 'boolean',
        'expires_at' => 'datetime',
    ];

    /**
     * Get the user that owns this agent key
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Generate a new agent key for a user
     */
    public static function generateKeyForUser(User $user, string $agentHost = 'default', int $hoursValid = 24): AgentKey
    {
        return static::create([
            'agent_key' => static::generateUniqueKey(),
            'name' => $user->username . '_' . Str::random(6),
            'agent_host' => $agentHost,
            'user_id' => $user->id,
            'status' => true,
            'expires_at' => Carbon::now()->addHours($hoursValid),
        ]);
    }

    /**
     * Generate a unique agent key
     */
    public static function generateUniqueKey(): string
    {
        do {
            $key = 'AK_' . Str::random(16);
        } while (static::where('agent_key', $key)->exists());

        return $key;
    }

    /**
     * Check if the agent key is expired
     */
    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at < now();
    }

    /**
     * Check if the agent key is active and not expired
     */
    public function isActive(): bool
    {
        return $this->status && !$this->isExpired();
    }

    /**
     * Scope to get only active agent keys
     */
    public function scopeActive($query)
    {
        return $query->where('status', true)
            ->where(function ($q) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            });
    }

    /**
     * Scope to get only expired agent keys
     */
    public function scopeExpired($query)
    {
        return $query->where('expires_at', '<', now());
    }
}
