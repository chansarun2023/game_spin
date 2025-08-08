<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Result;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class PointsController extends Controller
{
    /**
     * Get user points
     */
    public function getUserPoints(Request $request): JsonResponse
    {
        $request->validate([
            'user_id' => 'required|integer|exists:users,id',
        ]);

        $user = User::findOrFail($request->user_id);

        // Calculate points from recent results if needed
        $this->calculateUserPoints($user->id);

        // Refresh user object to get updated points
        $user->refresh();

        return response()->json([
            'success' => true,
            'data' => [
                'user_id' => $user->id,
                'current_points' => $user->getCurrentPoints(),
                'total_points' => $user->getTotalPoints(),
                'points_this_week' => $this->getPointsThisWeek($user->id),
            ],
            'message' => 'User points retrieved successfully',
        ]);
    }

    /**
     * Calculate points for a specific user
     */
    public function calculateUserPoints(int $userId): JsonResponse
    {
        $user = User::findOrFail($userId);

        // Get all results for the user that haven't been processed for points
        $results = Result::where('user_id', $userId)
            ->where('status', 'completed')
            ->where('points_calculated', false)
            ->get();

        $totalPointsEarned = 0;

        foreach ($results as $result) {
            $points = $this->calculatePointsFromResult($result);
            $totalPointsEarned += $points;

            // Mark result as processed
            $result->update(['points_calculated' => true]);
        }

        // Add points to user if any were earned
        if ($totalPointsEarned > 0) {
            $user->addPoints($totalPointsEarned);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'user_id' => $userId,
                'points_earned' => $totalPointsEarned,
                'current_points' => $user->getCurrentPoints(),
                'total_points' => $user->getTotalPoints(),
            ],
            'message' => 'Points calculated successfully',
        ]);
    }

    /**
     * Calculate points for all users
     */
    public function calculateAllUsersPoints(): JsonResponse
    {
        $users = User::all();
        $totalProcessed = 0;
        $totalPointsEarned = 0;

        foreach ($users as $user) {
            $results = Result::where('user_id', $user->id)
                ->where('status', 'completed')
                ->where('points_calculated', false)
                ->get();

            $userPointsEarned = 0;

            foreach ($results as $result) {
                $points = $this->calculatePointsFromResult($result);
                $userPointsEarned += $points;

                // Mark result as processed
                $result->update(['points_calculated' => true]);
            }

            if ($userPointsEarned > 0) {
                $user->addPoints($userPointsEarned);
                $totalPointsEarned += $userPointsEarned;
            }

            $totalProcessed++;
        }

        return response()->json([
            'success' => true,
            'data' => [
                'users_processed' => $totalProcessed,
                'total_points_earned' => $totalPointsEarned,
            ],
            'message' => 'Points calculated for all users successfully',
        ]);
    }

    /**
     * Get points earned this week for a user
     */
    private function getPointsThisWeek(int $userId): int
    {
        $startOfWeek = now()->startOfWeek();

        return Result::where('user_id', $userId)
            ->where('status', 'completed')
            ->where('created_at', '>=', $startOfWeek)
            ->get()
            ->sum(function ($result) {
                return $this->calculatePointsFromResult($result);
            });
    }

    /**
     * Calculate points from a spin result
     */
    private function calculatePointsFromResult(Result $result): int
    {
        // Base points for completing a spin
        $basePoints = 10;

        // Bonus points based on result type
        $bonusPoints = 0;

        switch ($result->result_english) {
            case 'Jackpot':
                $bonusPoints = 100;
                break;
            case 'Big Win':
                $bonusPoints = 50;
                break;
            case 'Win':
                $bonusPoints = 25;
                break;
            case 'Small Win':
                $bonusPoints = 15;
                break;
            default:
                $bonusPoints = 5;
                break;
        }

        return $basePoints + $bonusPoints;
    }
}
