<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use App\Models\PersonalAccessToken;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'username',
        'email',
        'password',
        'agent_id',
        'last_login_at',
        'status',
        'role_id',
        'points',
        'total_points',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'last_login_at' => 'datetime',
        'password' => 'hashed',
        'status' => 'boolean',
        'points' => 'integer',
        'total_points' => 'integer',
    ];

    /**
     * Get the agent keys for the user
     */
    public function agentKeys(): HasMany
    {
        return $this->hasMany(AgentKey::class);
    }

    /**
     * Get active agent keys for the user
     */
    public function activeAgentKeys(): HasMany
    {
        return $this->agentKeys()->where('status', true)->where('expires_at', '>', now());
    }

    /**
     * Generate new agent key for user
     */
    public function generateAgentKey(string $agentHost = 'default', int $hoursValid = 24): AgentKey
    {
        return AgentKey::generateKeyForUser($this, $agentHost, $hoursValid);
    }

    /**
     * Get the results for the user
     */
    public function results(): HasMany
    {
        return $this->hasMany(\App\Models\Result::class);
    }

    /**
     * Get the rewards for the user
     */
    public function rewards(): HasMany
    {
        return $this->hasMany(\App\Models\Reward::class);
    }

    /**
     * Get the user's current points
     */
    public function getCurrentPoints(): int
    {
        return $this->points;
    }

    /**
     * Get the user's total accumulated points
     */
    public function getTotalPoints(): int
    {
        return $this->total_points;
    }

    /**
     * Add points to user
     */
    public function addPoints(int $points): void
    {
        $this->increment('points', $points);
        $this->increment('total_points', $points);
    }

    /**
     * Deduct points from user
     */
    public function deductPoints(int $points): bool
    {
        if ($this->points >= $points) {
            $this->decrement('points', $points);
            return true;
        }
        return false;
    }

    /**
     * Check if user has enough points
     */
    public function hasEnoughPoints(int $points): bool
    {
        return $this->points >= $points;
    }

    /**
     * Create a token with unique identifier
     */
    public function createTokenWithIdentifier(string $name, array $abilities = ['*'], $deviceInfo = null): PersonalAccessToken
    {
        return PersonalAccessToken::createTokenWithIdentifier($this, $name, $abilities, $deviceInfo);
    }

    /**
     * Get all active tokens with unique identifiers
     */
    public function getActiveTokensWithIdentifiers(): \Illuminate\Database\Eloquent\Collection
    {
        return PersonalAccessToken::getActiveTokensForUser($this);
    }

    /**
     * Revoke token by unique identifier
     */
    public function revokeTokenByIdentifier(string $uniqueIdentifier): bool
    {
        return PersonalAccessToken::revokeByUniqueIdentifier($uniqueIdentifier);
    }
}
