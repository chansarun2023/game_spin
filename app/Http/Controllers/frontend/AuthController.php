<?php

namespace App\Http\Controllers\frontend;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserDataResource;
use App\Helpers\AuthHelper;
use App\Models\PersonalAccessToken;
use App\Models\AgentKey;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;
use Exception;

class AuthController extends Controller
{
    // Configuration constants
    private const REQUIRED_ROLE_ID = 6;
    private const TOKEN_EXPIRY_HOURS = 1;
    private const DEFAULT_CURRENCY = 'USD';
    private const DEFAULT_LANGUAGE = 'km';
    private const FRONTEND_BASE_URL = 'http://localhost:3000/login';
    private const TOKEN_EXPIRY_SECONDS = 3600;

    // Validation rules
    private const LOGIN_VALIDATION_RULES = [
        'username' => 'required|string|max:255',
        'password' => 'required|string',
        'lng' => 'nullable|string',
        'hideBar' => 'nullable|string',
        'gameCode' => 'nullable|string',
    ];

    private const GAME_LOGIN_VALIDATION_RULES = [
        'username' => 'required|string|max:255',
        'agent_key' => 'required|string|max:255',
    ];

    /**
     * Handle user login with game integration
     */
    public function login(Request $request): JsonResponse
    {
        return $this->handleStandardLogin($request, false);
    }

    /**
     * Handle single login with redirect link generation
     */
    public function singleLogin(Request $request): JsonResponse
    {
        return $this->handleStandardLogin($request, true);
    }

    /**
     * Legacy login method (kept for backward compatibility)
     */
    public function handleLogin(Request $request): JsonResponse
    {
        return $this->login($request);
    }

    /**
     * Login for game integration with username and agent_key
     */
    public function loginGame(Request $request): JsonResponse
    {
        try {
            $this->logGameLoginAttempt($request);

            // Validate input
            $validationResult = $this->validateRequest($request, self::GAME_LOGIN_VALIDATION_RULES);
            if (!$validationResult['success']) {
                return $validationResult['response'];
            }

            // Validate agent key and user
            $authResult = $this->validateGameCredentials($request);
            if (!$authResult['success']) {
                return $authResult['response'];
            }

            $user = $authResult['user'];
            $agentKey = $authResult['agent_key'];

            // Clean up expired tokens
            $this->cleanupExpiredTokens($user->id);

            // Check for existing valid token
            $existingToken = $this->getValidUserToken($user);
            if ($existingToken) {
                return $this->createGameLoginResponse($existingToken, $user, 'existing');
            }

            // Create new session token
            $tokenModel = $this->createGameSessionToken($user, $request);
            $this->updateLastLogin($user);

            $this->logGameLoginSuccess($request, $user, $tokenModel);

            return $this->createGameLoginResponse($tokenModel->plainTextToken, $user, 'new');

        } catch (Exception $e) {
            $this->logGameLoginError($e, $request);
            return $this->errorResponse('An unexpected error occurred during login', 500);
        }
    }

    /**
     * Logout user
     */
    public function logout(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            if (!$user) {
                return $this->errorResponse('No authenticated user found', 401);
            }

            $request->user()->currentAccessToken()->delete();
            return $this->successResponse(['message' => 'Logged out successfully']);

        } catch (Exception $e) {
            Log::error('Logout error: ' . $e->getMessage());
            return $this->errorResponse('An error occurred during logout', 500);
        }
    }

    /**
     * Logout from all devices
     */
    public function logoutAll(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            if (!$user) {
                return $this->errorResponse('No authenticated user found', 401);
            }

            AuthHelper::clearAllUserTokens($user->id);
            return $this->successResponse(['message' => 'Logged out from all devices successfully']);

        } catch (Exception $e) {
            Log::error('Logout all error: ' . $e->getMessage());
            return $this->errorResponse('An error occurred during logout', 500);
        }
    }

    /**
     * Get user tokens
     */
    public function getUserTokens(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            if (!$user) {
                return $this->errorResponse('No authenticated user found', 401);
            }

            $tokens = AuthHelper::getUserActiveTokens($user->id);
            return $this->successResponse(['tokens' => $tokens]);

        } catch (Exception $e) {
            Log::error('Get tokens error: ' . $e->getMessage());
            return $this->errorResponse('An error occurred while fetching tokens', 500);
        }
    }

    /**
     * Revoke specific token
     */
    public function revokeToken(Request $request, string $identifier): JsonResponse
    {
        try {
            $user = $request->user();
            if (!$user) {
                return $this->errorResponse('No authenticated user found', 401);
            }

            $success = $user->revokeTokenByIdentifier($identifier);

            if ($success) {
                return $this->successResponse(['message' => 'Token revoked successfully']);
            }

            return $this->errorResponse('Token not found or already revoked', 404);

        } catch (Exception $e) {
            Log::error('Revoke token error: ' . $e->getMessage());
            return $this->errorResponse('An error occurred while revoking token', 500);
        }
    }

    /**
     * Validate existing token and return user data if valid
     */
    public function validateToken(Request $request): JsonResponse
    {
        try {
            $token = $request->bearerToken();
            if (!$token) {
                return $this->errorResponse('No token provided', 401);
            }

            $personalAccessToken = PersonalAccessToken::findToken($token);
            if (!$personalAccessToken) {
                return $this->errorResponse('Invalid token', 401);
            }

            if ($this->isTokenExpired($personalAccessToken)) {
                return $this->errorResponse('Token expired', 401);
            }

            $user = $personalAccessToken->tokenable;
            if (!$user || !$user->status) {
                return $this->errorResponse('User not found or inactive', 401);
            }

            return $this->successResponse([
                'user' => new UserDataResource($user),
                'authenticated' => true,
                'token' => $token
            ]);

        } catch (Exception $e) {
            Log::error('Token validation error: ' . $e->getMessage());
            return $this->errorResponse('An error occurred during token validation', 500);
        }
    }

    /**
     * Check if user has valid session and extend if needed
     */
    public function checkSession(Request $request): JsonResponse
    {
        try {
            $validationResult = $this->validateRequest($request, self::GAME_LOGIN_VALIDATION_RULES);
            if (!$validationResult['success']) {
                return $validationResult['response'];
            }

            $authResult = $this->validateGameCredentials($request);
            if (!$authResult['success']) {
                return $authResult['response'];
            }

            $user = $authResult['user'];
            $existingToken = $this->getValidUserToken($user);

            if ($existingToken) {
                $this->extendTokenExpiry($user);
                $link = $this->buildFrontendLink($existingToken, $user);

                $this->logSessionCheckSuccess($request, $user);

                return response()->json([
                    'success' => true,
                    'link' => $link,
                    'message' => 'Existing session found and extended',
                    'session_status' => 'existing'
                ], 200);
            }

            return response()->json([
                'success' => false,
                'message' => 'No valid session found',
                'session_status' => 'expired'
            ], 200);

        } catch (Exception $e) {
            $this->logSessionCheckError($e);
            return $this->errorResponse('An unexpected error occurred during session check', 500);
        }
    }

    /**
     * Debug endpoint to check token status (for development only)
     */
    public function debugTokens(Request $request): JsonResponse
    {
        try {
            $validationResult = $this->validateRequest($request, ['username' => 'required|string|max:255']);
            if (!$validationResult['success']) {
                return $validationResult['response'];
            }

            $user = $this->findUserByUsername($request->username);
            if (!$user) {
                return $this->errorResponse('User not found or inactive', 401);
            }

            $tokenInfo = $this->getUserTokenInfo($user);

            return response()->json([
                'success' => true,
                'user' => [
                    'id' => $user->id,
                    'username' => $user->username,
                    'status' => $user->status,
                    'last_login_at' => $user->last_login_at,
                ],
                'tokens' => $tokenInfo['tokens'],
                'total_tokens' => $tokenInfo['total'],
                'active_tokens' => $tokenInfo['active'],
            ]);

        } catch (Exception $e) {
            Log::error('Debug tokens error: ' . $e->getMessage());
            return $this->errorResponse('An error occurred during debug', 500);
        }
    }

    // Private helper methods

    /**
     * Handle standard login flow
     */
    private function handleStandardLogin(Request $request, bool $generateLink): JsonResponse
    {
        try {
            $validationResult = $this->validateRequest($request, self::LOGIN_VALIDATION_RULES);
            if (!$validationResult['success']) {
                return $validationResult['response'];
            }

            $authResult = $this->authenticateUser($request);
            if (!$authResult['success']) {
                return $this->errorResponse($authResult['message'], $authResult['status']);
            }

            $user = $authResult['user'];
            $this->updateLastLogin($user);
            $this->clearAllUserSessions($user->id);

            $session = $this->createUserSession($user, $request);
            $gameData = $this->getGameData($request, $user->username);

            if ($generateLink) {
                $link = $this->generateRedirectLink($user, $session->id);
                return response()->json([
                    'success' => true,
                    'link' => $link,
                ], 200);
            }

            $response = [
                'userData' => new UserDataResource($user),
                'authenticated' => true,
                'accessToken' => AuthHelper::userGenerateAccessToken($user->id, $session->id),
                'game' => $gameData['success'] ? $gameData['data'] : null,
            ];

            return $this->successResponse($response);

        } catch (Exception $e) {
            $this->logLoginError($e);
            return $this->errorResponse('An unexpected error occurred', 500);
        }
    }

    /**
     * Validate request data
     */
    private function validateRequest(Request $request, array $rules): array
    {
        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return [
                'success' => false,
                'response' => $this->errorResponse('Validation failed', 422, $validator->errors())
            ];
        }

        return ['success' => true];
    }

    /**
     * Validate game credentials (agent key and user)
     */
    private function validateGameCredentials(Request $request): array
    {
        $agentKey = AgentKey::where('agent_key', $request->agent_key)
            ->where('status', true)
            ->where('expires_at', '>', now())
            ->first();

        if (!$agentKey) {
            $this->logInvalidAgentKey($request);
            return [
                'success' => false,
                'response' => $this->errorResponse('Invalid or expired agent key', 401)
            ];
        }

        $user = $this->findUserByUsername($request->username);
        if (!$user) {
            $this->logUserNotFound($request);
            return [
                'success' => false,
                'response' => $this->errorResponse('User not found or inactive', 401)
            ];
        }

        if ($agentKey->user_id && $agentKey->user_id !== $user->id) {
            $this->logUnauthorizedAgentKey($request, $user, $agentKey);
            return [
                'success' => false,
                'response' => $this->errorResponse('Agent key not authorized for this user', 403)
            ];
        }

        return [
            'success' => true,
            'user' => $user,
            'agent_key' => $agentKey
        ];
    }

    /**
     * Find user by username
     */
    private function findUserByUsername(string $username): ?User
    {
        return User::where('username', strtolower($username))
            ->where('status', true)
            ->first();
    }

    /**
     * Authenticate user with credentials
     */
    private function authenticateUser(Request $request): array
    {
        $credentials = $request->only('username', 'password');
        $credentials['username'] = strtolower($credentials['username']);

        if (!Auth::attempt($credentials)) {
            return [
                'success' => false,
                'message' => 'Your username or password is incorrect, please try again.',
                'status' => 401
            ];
        }

        $user = Auth::user();

        if (!$user->status) {
            return [
                'success' => false,
                'message' => 'Your account is disabled.',
                'status' => 401
            ];
        }

        if ($user->role_id != self::REQUIRED_ROLE_ID) {
            return [
                'success' => false,
                'message' => 'You are not authorized to access this system.',
                'status' => 401
            ];
        }

        return [
            'success' => true,
            'user' => $user
        ];
    }

    /**
     * Update user's last login timestamp
     */
    private function updateLastLogin(User $user): void
    {
        $user->update(['last_login_at' => Carbon::now()]);
    }

    /**
     * Clear all existing sessions for a user
     */
    private function clearAllUserSessions(int $userId): void
    {
        AuthHelper::clearAllUserTokens($userId);
        Log::info("Cleared all sessions for user: {$userId}");
    }

    /**
     * Create a new user session
     */
    private function createUserSession(User $user, Request $request): object
    {
        return (object) [
            'id' => uniqid(),
            'user_id' => $user->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'created_at' => now(),
        ];
    }

    /**
     * Get game data from external API
     */
    private function getGameData(Request $request, string $username): array
    {
        try {
            $params = [
                'lng' => $request->lng ?? self::DEFAULT_LANGUAGE,
                'hideBar' => $request->hideBar ?? 'true',
                'username' => $username,
                'keys' => config('app.api_key'),
                'game_code' => $request->gameCode,
            ];

            $response = Http::timeout(30)->post(config('app.base_api_url'), $params);

            if ($response->successful()) {
                $result = $response->json();
                return [
                    'success' => true,
                    'data' => $result['respBody']['result'] ?? null
                ];
            }

            return [
                'success' => false,
                'message' => 'Failed to connect to game service'
            ];

        } catch (Exception $e) {
            Log::error('Game API error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Game service temporarily unavailable'
            ];
        }
    }

    /**
     * Generate redirect link for single login
     */
    private function generateRedirectLink(User $user, string $sessionId): string
    {
        $baseUrl = config('app.dashboard_url', self::FRONTEND_BASE_URL);
        $queryParams = [
            'token' => AuthHelper::userGenerateAccessToken($user->id, $sessionId),
            'expiresIn' => self::TOKEN_EXPIRY_HOURS * 3600,
            'memberLogin' => $user->username,
            'currency' => self::DEFAULT_CURRENCY,
            'lng' => self::DEFAULT_LANGUAGE,
            'hideBar' => 'true',
        ];

        return $baseUrl . '?' . http_build_query($queryParams);
    }

    /**
     * Get valid token for user if exists
     */
    private function getValidUserToken(User $user): ?string
    {
        try {
            $activeTokens = $user->getActiveTokensWithIdentifiers();

            Log::info('Checking tokens for user', [
                'username' => $user->username,
                'token_count' => $activeTokens->count()
            ]);

            if ($activeTokens->isEmpty()) {
                Log::info('No active tokens found for user: ' . $user->username);
                return null;
            }

            $latestToken = $activeTokens->sortByDesc('created_at')->first();

            Log::info('Latest token info', [
                'username' => $user->username,
                'token_id' => $latestToken->id,
                'created_at' => $latestToken->created_at,
                'expires_at' => $latestToken->expires_at,
                'is_expired' => $latestToken->created_at->addHour()->isPast()
            ]);

            if ($latestToken->created_at->addHour()->isFuture()) {
                $this->extendTokenExpiry($user);
                $deviceInfo = PersonalAccessToken::getDeviceInfoFromRequest(request());
                $tokenModel = $user->createTokenWithIdentifier('game_session_extended', ['*'], $deviceInfo);

                Log::info('Token extended for user: ' . $user->username);
                return $tokenModel->plainTextToken;
            }

            Log::info('Token expired for user: ' . $user->username);
            return null;

        } catch (Exception $e) {
            Log::error('Error checking user token: ' . $e->getMessage(), [
                'username' => $user->username,
                'trace' => $e->getTraceAsString()
            ]);
            return null;
        }
    }

    /**
     * Check if token is expired
     */
    private function isTokenExpired(PersonalAccessToken $token): bool
    {
        if ($token->expires_at && $token->expires_at->isPast()) {
            return true;
        }

        return $token->created_at->addHour()->isPast();
    }

    /**
     * Extend token expiry by updating the created_at timestamp
     */
    private function extendTokenExpiry(User $user): void
    {
        try {
            $activeTokens = $user->getActiveTokensWithIdentifiers();

            if (!$activeTokens->isEmpty()) {
                $latestToken = $activeTokens->sortByDesc('created_at')->first();
                $latestToken->update([
                    'created_at' => now(),
                    'last_used_at' => now()
                ]);

                Log::info('Token expiry extended for user: ' . $user->username);
            }
        } catch (Exception $e) {
            Log::error('Error extending token expiry: ' . $e->getMessage());
        }
    }

    /**
     * Create game session token
     */
    private function createGameSessionToken(User $user, Request $request): PersonalAccessToken
    {
        $deviceInfo = PersonalAccessToken::getDeviceInfoFromRequest($request);
        return $user->createTokenWithIdentifier('game_session', ['*'], $deviceInfo);
    }

    /**
     * Create game login response
     */
    private function createGameLoginResponse(string $token, User $user, string $sessionType): JsonResponse
    {
        $link = $this->buildFrontendLink($token, $user);

        $response = [
            'success' => true,
            'link' => $link,
        ];

        if ($sessionType === 'new') {
            $response['message'] = 'New session created';
            $response['session_type'] = 'new';
        }

        return response()->json($response, 200);
    }

    /**
     * Build frontend link with token
     */
    private function buildFrontendLink(string $token, User $user): string
    {
        $queryParams = [
            'token' => $token,
            'expiresIn' => self::TOKEN_EXPIRY_SECONDS,
            'memberLogin' => $user->username,
            'currency' => self::DEFAULT_CURRENCY,
            'lng' => self::DEFAULT_LANGUAGE,
            'hideBar' => 'true',
        ];

        return self::FRONTEND_BASE_URL . '?' . http_build_query($queryParams);
    }

    /**
     * Get user token information for debug
     */
    private function getUserTokenInfo(User $user): array
    {
        $allTokens = PersonalAccessToken::where('tokenable_type', User::class)
            ->where('tokenable_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->get();

        $tokenInfo = $allTokens->map(function ($token) {
            return [
                'id' => $token->id,
                'name' => $token->name,
                'created_at' => $token->created_at,
                'expires_at' => $token->expires_at,
                'last_used_at' => $token->last_used_at,
                'is_expired' => $token->created_at->addHour()->isPast(),
                'has_plain_text' => !empty($token->plain_text_token),
                'unique_identifier' => $token->unique_identifier,
            ];
        })->toArray();

        return [
            'tokens' => $tokenInfo,
            'total' => $allTokens->count(),
            'active' => $allTokens->where('created_at', '>', now()->subHour())->count(),
        ];
    }

    /**
     * Clean up expired tokens for a user
     */
    private function cleanupExpiredTokens(int $userId): void
    {
        try {
            $deletedCount = PersonalAccessToken::where('tokenable_type', User::class)
                ->where('tokenable_id', $userId)
                ->where('created_at', '<', now()->subHour())
                ->delete();

            if ($deletedCount > 0) {
                Log::info("Cleaned up {$deletedCount} expired tokens for user ID: {$userId}");
            }
        } catch (Exception $e) {
            Log::error('Error cleaning up expired tokens: ' . $e->getMessage());
        }
    }

    // Logging methods

    private function logGameLoginAttempt(Request $request): void
    {
        Log::info('Login game attempt', [
            'username' => $request->username,
            'agent_key' => $request->agent_key,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent()
        ]);
    }

    private function logInvalidAgentKey(Request $request): void
    {
        Log::warning('Invalid agent key', [
            'agent_key' => $request->agent_key,
            'ip' => $request->ip()
        ]);
    }

    private function logUserNotFound(Request $request): void
    {
        Log::warning('User not found or inactive', [
            'username' => $request->username,
            'ip' => $request->ip()
        ]);
    }

    private function logUnauthorizedAgentKey(Request $request, User $user, AgentKey $agentKey): void
    {
        Log::warning('Agent key not authorized for user', [
            'username' => $user->username,
            'agent_key' => $request->agent_key,
            'agent_user_id' => $agentKey->user_id,
            'user_id' => $user->id
        ]);
    }

    private function logGameLoginSuccess(Request $request, User $user, PersonalAccessToken $tokenModel): void
    {
        Log::info('Game login successful - new token created', [
            'username' => $user->username,
            'agent_key' => $request->agent_key,
            'ip_address' => $request->ip(),
            'token_length' => strlen($tokenModel->plainTextToken),
            'token_id' => $tokenModel->id
        ]);
    }

    private function logGameLoginError(Exception $e, Request $request): void
    {
        Log::error('Game login error: ' . $e->getMessage(), [
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString(),
            'username' => $request->username ?? 'unknown',
            'agent_key' => $request->agent_key ?? 'unknown'
        ]);
    }

    private function logLoginError(Exception $e): void
    {
        Log::error('Login error: ' . $e->getMessage(), [
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString()
        ]);
    }

    private function logSessionCheckSuccess(Request $request, User $user): void
    {
        Log::info('Session check - using existing token', [
            'username' => $user->username,
            'agent_key' => $request->agent_key,
            'ip_address' => $request->ip()
        ]);
    }

    private function logSessionCheckError(Exception $e): void
    {
        Log::error('Session check error: ' . $e->getMessage(), [
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString()
        ]);
    }

    // Response helper methods

    private function successResponse($data, int $status = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $data
        ], $status);
    }

    private function errorResponse(string $message, int $status = 400, $errors = null): JsonResponse
    {
        $response = [
            'success' => false,
            'message' => $message
        ];

        if ($errors) {
            $response['errors'] = $errors;
        }

        return response()->json($response, $status);
    }
}
