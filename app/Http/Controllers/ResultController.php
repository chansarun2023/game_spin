<?php

namespace App\Http\Controllers;

use App\Models\Result;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class ResultController extends Controller
{
    /**
     * Store a new spin result
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        try {
            // Validate the incoming request
            $validator = Validator::make($request->all(), [
                'user_id' => 'nullable|integer|exists:users,id',
                'segment_index' => 'required|integer|min:0|max:14',
                'result_english' => 'required|string|max:255',
                'result_khmer' => 'required|string|max:255',
                'result_color' => 'required|string|max:7', // hex color
                'spin_angle' => 'required|numeric|min:0|max:9999',
                'game_code' => 'nullable|string|max:255',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Prepare spin data
            $spinData = [
                'segment_index' => $request->segment_index,
                'result_english' => $request->result_english,
                'result_khmer' => $request->result_khmer,
                'result_color' => $request->result_color,
                'spin_angle' => $request->spin_angle,
                'timestamp' => now()->toISOString(),
                'session_id' => $request->hasSession() ? $request->session()->getId() : null,
            ];

            // Get user ID from authentication or request
            $userId = Auth::id() ?? $request->input('user_id');

            // Log authentication status for debugging
            \Log::info('Result creation - Auth status', [
                'auth_check' => Auth::check(),
                'auth_id' => Auth::id(),
                'request_user_id' => $request->input('user_id'),
                'final_user_id' => $userId,
                'bearer_token' => $request->bearerToken() ? 'present' : 'absent',
            ]);

            // Create the result record
            $result = Result::create([
                'user_id' => $userId, // Will be set from authenticated user or request
                'game_type' => 'spin_wheel',
                'game_code' => $request->game_code ?? Str::random(8),
                'spin_data' => $spinData,
                'result_english' => $request->result_english,
                'result_khmer' => $request->result_khmer,
                'result_color' => $request->result_color,
                'segment_index' => $request->segment_index,
                'spin_angle' => $request->spin_angle,
                'status' => 'completed',
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            // Log for debugging
            \Log::info('Result created', [
                'id' => $result->id,
                'user_id' => $result->user_id,
                'result_english' => $result->result_english,
                'created_at' => $result->created_at
            ]);

            // Verify the result was actually saved
            $savedResult = Result::find($result->id);
            if (!$savedResult) {
                \Log::error('Result was not saved to database', ['result_id' => $result->id]);
                throw new \Exception('Failed to save result to database');
            }

            // Automatically calculate and add points if user is authenticated
            if ($userId) {
                $pointsEarned = $this->extractPointsFromResult($result->result_english);

                if ($pointsEarned > 0) {
                    // Get user and add points using real-time service
                    $user = \App\Models\User::find($userId);
                    if ($user) {
                        // Use real-time points service for broadcasting
                        $realTimeService = app(\App\Services\RealTimePointsService::class);
                        $realTimeService->processPointsEarned($user, $result, $pointsEarned);
                    }
                }
            }

            // Calculate points earned for response
            $pointsEarned = $userId ? $this->extractPointsFromResult($result->result_english) : 0;

            return response()->json([
                'success' => true,
                'message' => 'Spin result stored successfully',
                'data' => [
                    'id' => $result->id,
                    'game_code' => $result->game_code,
                    'result' => [
                        'english' => $result->result_english,
                        'khmer' => $result->result_khmer,
                        'color' => $result->result_color,
                        'segment_index' => $result->segment_index,
                    ],
                    'points_earned' => $pointsEarned,
                    'created_at' => $result->created_at,
                ]
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to store spin result',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get user's spin history
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = Result::gameType('spin_wheel')
                ->completed()
                ->orderBy('created_at', 'desc');

            // If user is authenticated, get their results
            if (Auth::check()) {
                $query->forUser(Auth::id());
            } else {
                // For guests, get results from their session/IP (last 24 hours)
                $query->where('ip_address', $request->ip())
                    ->where('created_at', '>=', now()->subDay());
            }

            // Pagination
            $perPage = min($request->get('per_page', 10), 50); // Max 50 per page
            $results = $query->paginate($perPage);

            // Format the results
            $formattedResults = $results->map(function ($result) {
                return [
                    'id' => $result->id,
                    'game_code' => $result->game_code,
                    'result' => [
                        'english' => $result->result_english,
                        'khmer' => $result->result_khmer,
                        'color' => $result->result_color,
                        'segment_index' => $result->segment_index,
                    ],
                    'spin_angle' => $result->spin_angle,
                    'created_at' => $result->created_at,
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $formattedResults,
                'pagination' => [
                    'current_page' => $results->currentPage(),
                    'last_page' => $results->lastPage(),
                    'per_page' => $results->perPage(),
                    'total' => $results->total(),
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve spin history',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get statistics for spin results
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function statistics(Request $request): JsonResponse
    {
        try {
            $query = Result::gameType('spin_wheel')->completed();

            // If user is authenticated, get their stats
            if (Auth::check()) {
                $query->forUser(Auth::id());
            } else {
                // For guests, get stats from their session/IP (last 30 days)
                $query->where('ip_address', $request->ip())
                    ->where('created_at', '>=', now()->subDays(30));
            }

            // Get segment statistics
            $segmentStats = $query->selectRaw('segment_index, result_english, result_khmer, COUNT(*) as count')
                ->groupBy(['segment_index', 'result_english', 'result_khmer'])
                ->orderBy('segment_index')
                ->get();

            // Total spins
            $totalSpins = $query->count();

            // Recent spins (last 7 days)
            $recentSpins = $query->where('created_at', '>=', now()->subWeek())->count();

            return response()->json([
                'success' => true,
                'data' => [
                    'total_spins' => $totalSpins,
                    'recent_spins' => $recentSpins,
                    'segment_statistics' => $segmentStats->map(function ($stat) use ($totalSpins) {
                        return [
                            'segment_index' => $stat->segment_index,
                            'result_english' => $stat->result_english,
                            'result_khmer' => $stat->result_khmer,
                            'count' => $stat->count,
                            'percentage' => $totalSpins > 0 ? round(($stat->count / $totalSpins) * 100, 2) : 0,
                        ];
                    }),
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve statistics',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get a specific result by ID or game code
     *
     * @param string $identifier
     * @return JsonResponse
     */
    public function show(string $identifier): JsonResponse
    {
        try {
            // Try to find by ID first, then by game_code
            $result = Result::where('id', $identifier)
                ->orWhere('game_code', $identifier)
                ->gameType('spin_wheel')
                ->completed()
                ->first();

            if (!$result) {
                return response()->json([
                    'success' => false,
                    'message' => 'Spin result not found'
                ], 404);
            }

            // Check if user has access to this result
            if (Auth::check() && $result->user_id && $result->user_id !== Auth::id()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied'
                ], 403);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $result->id,
                    'game_code' => $result->game_code,
                    'result' => [
                        'english' => $result->result_english,
                        'khmer' => $result->result_khmer,
                        'color' => $result->result_color,
                        'segment_index' => $result->segment_index,
                    ],
                    'spin_data' => $result->spin_data,
                    'spin_angle' => $result->spin_angle,
                    'created_at' => $result->created_at,
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve spin result',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Calculate total points for users based on result_english column
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function calculatePoints(Request $request): JsonResponse
    {
        try {
            $query = Result::gameType('spin_wheel')
                ->completed()
                ->whereNotNull('user_id');

            // Apply date filters if provided
            if ($request->has('start_date')) {
                $query->where('created_at', '>=', $request->start_date);
            }
            if ($request->has('end_date')) {
                $query->where('created_at', '<=', $request->end_date);
            }

            // Get all results with user_id
            $results = $query->get();

            // Calculate points for each user
            $userPoints = [];
            $totalPoints = 0;

            foreach ($results as $result) {
                $points = $this->extractPointsFromResult($result->result_english);

                if (!isset($userPoints[$result->user_id])) {
                    $userPoints[$result->user_id] = [
                        'user_id' => $result->user_id,
                        'total_points' => 0,
                        'spin_count' => 0,
                        'results' => []
                    ];
                }

                $userPoints[$result->user_id]['total_points'] += $points;
                $userPoints[$result->user_id]['spin_count']++;
                $userPoints[$result->user_id]['results'][] = [
                    'id' => $result->id,
                    'game_code' => $result->game_code,
                    'result_english' => $result->result_english,
                    'points' => $points,
                    'created_at' => $result->created_at
                ];

                $totalPoints += $points;
            }

            // Sort by total points (descending)
            usort($userPoints, function ($a, $b) {
                return $b['total_points'] - $a['total_points'];
            });

            // Get user details if requested
            if ($request->boolean('include_user_details')) {
                $userIds = array_column($userPoints, 'user_id');
                $users = \App\Models\User::whereIn('id', $userIds)->get()->keyBy('id');

                foreach ($userPoints as &$userPoint) {
                    $user = $users->get($userPoint['user_id']);
                    $userPoint['user'] = $user ? [
                        'id' => $user->id,
                        'username' => $user->username,
                        'email' => $user->email,
                        'name' => $user->name
                    ] : null;
                }
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'total_points_all_users' => $totalPoints,
                    'total_users' => count($userPoints),
                    'user_points' => array_values($userPoints),
                    'summary' => [
                        'average_points_per_user' => count($userPoints) > 0 ? round($totalPoints / count($userPoints), 2) : 0,
                        'highest_points' => count($userPoints) > 0 ? $userPoints[0]['total_points'] : 0,
                        'lowest_points' => count($userPoints) > 0 ? end($userPoints)['total_points'] : 0,
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to calculate points',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Calculate points for a specific user
     *
     * @param Request $request
     * @param int $userId
     * @return JsonResponse
     */
    public function calculateUserPoints(Request $request, int $userId): JsonResponse
    {
        try {
            $query = Result::gameType('spin_wheel')
                ->completed()
                ->where('user_id', $userId);

            // Apply date filters if provided
            if ($request->has('start_date')) {
                $query->where('created_at', '>=', $request->start_date);
            }
            if ($request->has('end_date')) {
                $query->where('created_at', '<=', $request->end_date);
            }

            $results = $query->orderBy('created_at', 'desc')->get();

            $totalPoints = 0;
            $spinCount = $results->count();
            $detailedResults = [];

            foreach ($results as $result) {
                $points = $this->extractPointsFromResult($result->result_english);
                $totalPoints += $points;

                $detailedResults[] = [
                    'id' => $result->id,
                    'game_code' => $result->game_code,
                    'result_english' => $result->result_english,
                    'result_khmer' => $result->result_khmer,
                    'points' => $points,
                    'segment_index' => $result->segment_index,
                    'created_at' => $result->created_at
                ];
            }

            // Get user details
            $user = \App\Models\User::find($userId);

            return response()->json([
                'success' => true,
                'data' => [
                    'user' => $user ? [
                        'id' => $user->id,
                        'username' => $user->username,
                        'email' => $user->email,
                        'name' => $user->name
                    ] : null,
                    'points_summary' => [
                        'total_points' => $totalPoints,
                        'spin_count' => $spinCount,
                        'average_points_per_spin' => $spinCount > 0 ? round($totalPoints / $spinCount, 2) : 0,
                    ],
                    'detailed_results' => $detailedResults
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to calculate user points',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Extract points from result_english string
     *
     * @param string $resultEnglish
     * @return int
     */
    private function extractPointsFromResult(string $resultEnglish): int
    {
        // Extract numbers from strings like "ពិន្ទុ ២៥" (Points 25)
        // First try to extract Khmer numerals
        if (preg_match('/ពិន្ទុ\s*([០-៩]+)/u', $resultEnglish, $matches)) {
            $khmerNumerals = $matches[1];
            return $this->convertKhmerToArabic($khmerNumerals);
        }

        // Try to extract Arabic numerals
        if (preg_match('/ពិន្ទុ\s*(\d+)/u', $resultEnglish, $matches)) {
            return (int) $matches[1];
        }

        // Try to extract any number
        if (preg_match('/(\d+)/', $resultEnglish, $matches)) {
            return (int) $matches[1];
        }

        return 0;
    }

    /**
     * Convert Khmer numerals to Arabic numerals
     *
     * @param string $khmerNumerals
     * @return int
     */
    private function convertKhmerToArabic(string $khmerNumerals): int
    {
        $khmerToArabic = [
            '០' => 0,
            '១' => 1,
            '២' => 2,
            '៣' => 3,
            '៤' => 4,
            '៥' => 5,
            '៦' => 6,
            '៧' => 7,
            '៨' => 8,
            '៩' => 9
        ];

        $result = '';
        for ($i = 0; $i < strlen($khmerNumerals); $i++) {
            $char = mb_substr($khmerNumerals, $i, 1, 'UTF-8');
            if (isset($khmerToArabic[$char])) {
                $result .= $khmerToArabic[$char];
            }
        }

        return (int) $result;
    }


}
