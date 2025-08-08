<?php

namespace App\Helpers;

use App\Models\User;
use App\Models\PersonalAccessToken;
use Illuminate\Support\Str;
use Carbon\Carbon;

class AuthHelper
{
    /**
     * Generate access token for user
     *
     * @param int $userId
     * @param string|null $sessionId
     * @return string
     */
    public static function userGenerateAccessToken(int $userId, ?string $sessionId = null): string
    {
        $user = User::find($userId);

        if (!$user) {
            throw new \InvalidArgumentException('User not found');
        }

        $tokenName = 'api-token-' . Str::random(8);
        $abilities = ['*'];

        $deviceInfo = [
            'session_id' => $sessionId,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'created_at' => now()->toISOString(),
        ];

        $token = $user->createTokenWithIdentifier($tokenName, $abilities, $deviceInfo);

        return $token->plainTextToken;
    }

    /**
     * Validate access token
     *
     * @param string $token
     * @return bool
     */
    public static function validateAccessToken(string $token): bool
    {
        try {
            $personalAccessToken = PersonalAccessToken::findToken($token);

            if (!$personalAccessToken) {
                return false;
            }

            // Check if token is expired
            if ($personalAccessToken->expires_at && $personalAccessToken->expires_at->isPast()) {
                return false;
            }

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Revoke access token
     *
     * @param string $token
     * @return bool
     */
    public static function revokeAccessToken(string $token): bool
    {
        try {
            $personalAccessToken = PersonalAccessToken::findToken($token);

            if ($personalAccessToken) {
                $personalAccessToken->delete();
                return true;
            }

            return false;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Clear all tokens for a user
     *
     * @param int $userId
     * @return int
     */
    public static function clearAllUserTokens(int $userId): int
    {
        return PersonalAccessToken::where('tokenable_id', $userId)
            ->where('tokenable_type', User::class)
            ->delete();
    }

    /**
     * Get active tokens for user
     *
     * @param int $userId
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function getUserActiveTokens(int $userId)
    {
        $user = User::find($userId);

        if (!$user) {
            return collect();
        }

        return $user->getActiveTokensWithIdentifiers();
    }
}
