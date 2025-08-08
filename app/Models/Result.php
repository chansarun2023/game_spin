<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Result extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'game_type',
        'game_code',
        'spin_data',
        'result_english',
        'result_khmer',
        'result_color',
        'segment_index',
        'spin_angle',
        'status',
        'ip_address',
        'user_agent',
        'points_calculated',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'spin_data' => 'array',
        'spin_angle' => 'decimal:2',
        'segment_index' => 'integer',
        'points_calculated' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the user that owns the result.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope a query to only include results for a specific game type.
     */
    public function scopeGameType($query, string $gameType)
    {
        return $query->where('game_type', $gameType);
    }

    /**
     * Scope a query to only include results for a specific user.
     */
    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope a query to only include completed results.
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }
}
