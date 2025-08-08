<?php

namespace App\Services;

use App\Models\User;
use App\Models\Result;
use App\Events\PointsEarned;
use App\Events\LeaderboardUpdated;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class RealTimePointsService
{
    /**
     * Process points earned and broadcast real-time updates
     */
    public function processPointsEarned(User $user, Result $result, int $pointsEarned): void
    {
        // Add points to user
        $user->addPoints($pointsEarned);

        // Get updated points
        $newTotalPoints = $user->getCurrentPoints();

        // Mark result as points calculated
        $result->update(['points_calculated' => true]);

        // Broadcast points earned event
        event(new PointsEarned($user, $result, $pointsEarned, $newTotalPoints));

        // Update leaderboard cache and broadcast
        $this->updateLeaderboard();

        // Log the transaction
        \Log::info('Real-time points processed', [
            'user_id' => $user->id,
            'username' => $user->username,
            'points_earned' => $pointsEarned,
            'new_total_points' => $newTotalPoints,
            'result_id' => $result->id,
        ]);
    }

    /**
     * Get real-time leaderboard data
     */
    public function getLeaderboard(int $limit = 10, string $timeframe = 'all'): array
    {
        $cacheKey = "leaderboard_{$timeframe}_{$limit}";

        return Cache::remember($cacheKey, 60, function () use ($limit, $timeframe) {
            $query = User::select('id', 'username', 'name', 'points', 'total_points')
                ->where('points', '>', 0)
                ->orderBy('points', 'desc')
                ->limit($limit);

            // Apply timeframe filter
            switch ($timeframe) {
                case 'today':
                    $query->whereHas('results', function ($q) {
                        $q->whereDate('created_at', today());
                    });
                    break;
                case 'week':
                    $query->whereHas('results', function ($q) {
                        $q->where('created_at', '>=', now()->subWeek());
                    });
                    break;
                case 'month':
                    $query->whereHas('results', function ($q) {
                        $q->where('created_at', '>=', now()->subMonth());
                    });
                    break;
                case 'all':
                default:
                    // No additional filter for all time
                    break;
            }

            $users = $query->get();

            return $users->map(function ($user, $index) {
                return [
                    'rank' => $index + 1,
                    'user_id' => $user->id,
                    'username' => $user->username,
                    'name' => $user->name,
                    'points' => $user->points,
                    'total_points' => $user->total_points,
                ];
            })->toArray();
        });
    }

    /**
     * Update leaderboard and broadcast changes
     */
    public function updateLeaderboard(): void
    {
        // Clear leaderboard cache
        Cache::forget('leaderboard_all_10');
        Cache::forget('leaderboard_today_10');
        Cache::forget('leaderboard_week_10');
        Cache::forget('leaderboard_month_10');

        // Get updated leaderboard
        $leaderboardData = [
            'all_time' => $this->getLeaderboard(10, 'all'),
            'today' => $this->getLeaderboard(10, 'today'),
            'this_week' => $this->getLeaderboard(10, 'week'),
            'this_month' => $this->getLeaderboard(10, 'month'),
        ];

        // Broadcast leaderboard update
        event(new LeaderboardUpdated($leaderboardData));
    }

    /**
     * Get user's real-time points status
     */
    public function getUserPointsStatus(User $user): array
    {
        $todayPoints = $this->getUserPointsForTimeframe($user, 'today');
        $weekPoints = $this->getUserPointsForTimeframe($user, 'week');
        $monthPoints = $this->getUserPointsForTimeframe($user, 'month');

        return [
            'current_points' => $user->getCurrentPoints(),
            'total_points' => $user->getTotalPoints(),
            'today_points' => $todayPoints,
            'week_points' => $weekPoints,
            'month_points' => $monthPoints,
            'rank' => $this->getUserRank($user),
            'last_updated' => now()->toISOString(),
        ];
    }

    /**
     * Get user's points for specific timeframe
     */
    private function getUserPointsForTimeframe(User $user, string $timeframe): int
    {
        $query = Result::where('user_id', $user->id)
            ->where('points_calculated', true);

        switch ($timeframe) {
            case 'today':
                $query->whereDate('created_at', today());
                break;
            case 'week':
                $query->where('created_at', '>=', now()->subWeek());
                break;
            case 'month':
                $query->where('created_at', '>=', now()->subMonth());
                break;
        }

        return $query->get()->sum(function ($result) {
            return $this->extractPointsFromResult($result->result_english);
        });
    }

    /**
     * Get user's current rank
     */
    private function getUserRank(User $user): int
    {
        $rank = User::where('points', '>', $user->points)->count();
        return $rank + 1;
    }

    /**
     * Extract points from result_english string
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

    /**
     * Get real-time statistics
     */
    public function getRealTimeStats(): array
    {
        $cacheKey = 'realtime_stats';

        return Cache::remember($cacheKey, 30, function () {
            $totalUsers = User::where('points', '>', 0)->count();
            $totalPoints = User::sum('points');
            $todaySpins = Result::whereDate('created_at', today())->count();
            $todayPoints = Result::whereDate('created_at', today())
                ->where('points_calculated', true)
                ->get()
                ->sum(function ($result) {
                    return $this->extractPointsFromResult($result->result_english);
                });

            return [
                'total_users_with_points' => $totalUsers,
                'total_points_in_system' => $totalPoints,
                'today_spins' => $todaySpins,
                'today_points_earned' => $todayPoints,
                'average_points_per_user' => $totalUsers > 0 ? round($totalPoints / $totalUsers, 2) : 0,
                'last_updated' => now()->toISOString(),
            ];
        });
    }
}
