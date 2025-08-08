<?php

namespace App\Models;

use Laravel\Sanctum\PersonalAccessToken as SanctumPersonalAccessToken;
use Illuminate\Support\Str;
use Carbon\Carbon;

class PersonalAccessToken extends SanctumPersonalAccessToken
{
    protected $fillable = [
        'tokenable_type',
        'tokenable_id',
        'name',
        'token',
        'unique_identifier',
        'device_type',
        'device_info',
        'session_id',
        'abilities',
        'expires_at',
        'plain_text_token',
    ];

    protected $casts = [
        'abilities' => 'json',
        'last_used_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    /**
     * Generate a unique identifier for the token
     */
    public static function generateUniqueIdentifier(): string
    {
        return 'token_' . time() . '_' . Str::random(16);
    }

    /**
     * Create a new token with unique identifier
     */
    public static function createTokenWithIdentifier($tokenable, string $name, array $abilities = ['*'], $deviceInfo = null): static
    {
        $plainTextToken = Str::random(40);

        $token = static::create([
            'tokenable_type' => get_class($tokenable),
            'tokenable_id' => $tokenable->id,
            'name' => $name,
            'token' => hash('sha256', $plainTextToken),
            'unique_identifier' => static::generateUniqueIdentifier(),
            'device_type' => $deviceInfo['device_type'] ?? null,
            'device_info' => $deviceInfo['device_info'] ?? null,
            'session_id' => $deviceInfo['session_id'] ?? Str::uuid(),
            'abilities' => $abilities,
            'plain_text_token' => $plainTextToken, // Store plain text token temporarily
        ]);

        $token->plainTextToken = $plainTextToken;

        return $token;
    }

    /**
     * Get plain text token for a token record
     */
    public function getPlainTextToken(): ?string
    {
        return $this->plain_text_token;
    }

    /**
     * Find token by plain text token
     */
    public static function findByPlainTextToken(string $plainTextToken): ?static
    {
        return static::where('plain_text_token', $plainTextToken)->first();
    }

    /**
     * Get token by unique identifier
     */
    public static function findByUniqueIdentifier(string $uniqueIdentifier): ?static
    {
        return static::where('unique_identifier', $uniqueIdentifier)->first();
    }

    /**
     * Get active tokens for a user
     */
    public static function getActiveTokensForUser($user): \Illuminate\Database\Eloquent\Collection
    {
        return static::where('tokenable_type', get_class($user))
            ->where('tokenable_id', $user->id)
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', Carbon::now());
            })
            ->get();
    }

    /**
     * Revoke token by unique identifier
     */
    public static function revokeByUniqueIdentifier(string $uniqueIdentifier): bool
    {
        $token = static::findByUniqueIdentifier($uniqueIdentifier);
        return $token ? $token->delete() : false;
    }

    /**
     * Get device information from request
     */
    public static function getDeviceInfoFromRequest($request): array
    {
        $userAgent = $request->header('User-Agent', '');

        // Simple device detection
        $deviceType = 'unknown';
        if (preg_match('/Mobile|Android|iPhone|iPad/', $userAgent)) {
            $deviceType = 'mobile';
        } elseif (preg_match('/Windows|Mac|Linux/', $userAgent)) {
            $deviceType = 'desktop';
        }

        return [
            'device_type' => $deviceType,
            'device_info' => $userAgent,
            'session_id' => $request->header('X-Session-ID') ?? Str::uuid(),
        ];
    }
}
