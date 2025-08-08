<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Reward extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'product_id',
        'points_spent',
        'status',
        'claimed_at',
        'used_at',
        'expires_at',
        'notes',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'claimed_at' => 'datetime',
        'used_at' => 'datetime',
        'expires_at' => 'datetime',
        'points_spent' => 'integer',
    ];

    /**
     * Get the user that owns the reward.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the product associated with the reward.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Scope a query to only include claimed rewards.
     */
    public function scopeClaimed($query)
    {
        return $query->where('status', 'claimed');
    }

    /**
     * Scope a query to only include used rewards.
     */
    public function scopeUsed($query)
    {
        return $query->where('status', 'used');
    }

    /**
     * Scope a query to only include expired rewards.
     */
    public function scopeExpired($query)
    {
        return $query->where('status', 'expired');
    }

    /**
     * Scope a query to only include active rewards (not expired).
     */
    public function scopeActive($query)
    {
        return $query->where(function ($q) {
            $q->where('expires_at', '>', now())
                ->orWhereNull('expires_at');
        });
    }
}
