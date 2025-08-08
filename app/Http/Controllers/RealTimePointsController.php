<?php

namespace App\Http\Controllers;

use App\Services\RealTimePointsService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class RealTimePointsController extends Controller
{
    protected $realTimePointsService;

    public function __construct(RealTimePointsService $realTimePointsService)
    {
        $this->realTimePointsService = $realTimePointsService;
    }

    /**
     * Get real-time leaderboard
     */
    public function getLeaderboard(Request $request): JsonResponse
    {
        try {
            $limit = min($request->get('limit', 10), 50); // Max 50
            $timeframe = $request->get('timeframe', 'all');

            // Validate timeframe
            $validTimeframes = ['all', 'today', 'week', 'month'];
            if (!in_array($timeframe, $validTimeframes)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid timeframe. Valid options: ' . implode(', ', $validTimeframes)
                ], 422);
            }

            $leaderboard = $this->realTimePointsService->getLeaderboard($limit, $timeframe);

            return response()->json([
                'success' => true,
                'data' => [
                    'leaderboard' => $leaderboard,
                    'timeframe' => $timeframe,
                    'limit' => $limit,
                    'timestamp' => now()->toISOString(),
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve leaderboard',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get user's real-time points status
     */
    public function getUserPointsStatus(Request $request): JsonResponse
    {
        try {
            if (!Auth::check()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Authentication required'
                ], 401);
            }

            $user = Auth::user();
            $pointsStatus = $this->realTimePointsService->getUserPointsStatus($user);

            return response()->json([
                'success' => true,
                'data' => $pointsStatus
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve user points status',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get real-time statistics
     */
    public function getRealTimeStats(Request $request): JsonResponse
    {
        try {
            $stats = $this->realTimePointsService->getRealTimeStats();

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve real-time statistics',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get comprehensive real-time data (leaderboard + stats + user status)
     */
    public function getRealTimeData(Request $request): JsonResponse
    {
        try {
            $limit = min($request->get('limit', 10), 50);
            $timeframe = $request->get('timeframe', 'all');

            // Validate timeframe
            $validTimeframes = ['all', 'today', 'week', 'month'];
            if (!in_array($timeframe, $validTimeframes)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid timeframe. Valid options: ' . implode(', ', $validTimeframes)
                ], 422);
            }

            $data = [
                'leaderboard' => $this->realTimePointsService->getLeaderboard($limit, $timeframe),
                'statistics' => $this->realTimePointsService->getRealTimeStats(),
                'timeframe' => $timeframe,
                'limit' => $limit,
                'timestamp' => now()->toISOString(),
            ];

            // Add user status if authenticated
            if (Auth::check()) {
                $data['user_status'] = $this->realTimePointsService->getUserPointsStatus(Auth::user());
            }

            return response()->json([
                'success' => true,
                'data' => $data
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve real-time data',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Force update leaderboard (admin only)
     */
    public function forceUpdateLeaderboard(Request $request): JsonResponse
    {
        try {
            // Check if user is admin (you can customize this based on your role system)
            if (!Auth::check() || Auth::user()->role_id !== 1) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access'
                ], 403);
            }

            $this->realTimePointsService->updateLeaderboard();

            return response()->json([
                'success' => true,
                'message' => 'Leaderboard updated successfully',
                'data' => [
                    'leaderboard' => $this->realTimePointsService->getLeaderboard(10, 'all'),
                    'timestamp' => now()->toISOString(),
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update leaderboard',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get broadcasting channels info for frontend
     */
    public function getBroadcastingInfo(Request $request): JsonResponse
    {
        try {
            $channels = [
                'points_updates' => 'points-updates',
                'leaderboard_updates' => 'leaderboard-updates',
            ];

            // Add private channel if user is authenticated
            if (Auth::check()) {
                $channels['user_updates'] = 'user.' . Auth::id();
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'channels' => $channels,
                    'broadcast_driver' => config('broadcasting.default'),
                    'pusher_config' => config('broadcasting.default') === 'pusher' ? [
                        'key' => config('broadcasting.connections.pusher.key'),
                        'cluster' => config('broadcasting.connections.pusher.options.cluster'),
                        'encrypted' => config('broadcasting.connections.pusher.options.encrypted'),
                    ] : null,
                    'timestamp' => now()->toISOString(),
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve broadcasting information',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }
}
