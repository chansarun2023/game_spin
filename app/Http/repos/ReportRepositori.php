<?php

namespace App\Http\Repositories;

use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ReportRepositori
{
    /**
     * Get total points for all users from result_english using native query
     * បន្ទោបពិន្ទុសរុបសម្រាប់អ្នកប្រើប្រាស់ទាំងអស់ពីជួរ result_english ដោយប្រើ native query
     *
     * @return array
     */
    public function getTotalPointsForAllUsers()
    {
        $sql = "
            SELECT
                u.id as user_id,
                u.name,
                u.username,
                COUNT(r.id) as total_games,
                COALESCE(SUM(
                    CASE
                        WHEN r.result_english REGEXP '^Prize [0-9]+$'
                        THEN CAST(REGEXP_REPLACE(r.result_english, '[^0-9]', '') AS UNSIGNED)
                        ELSE 0
                    END
                ), 0) as total_points
            FROM users u
            LEFT JOIN results r ON u.id = r.user_id AND r.status = 'completed'
            GROUP BY u.id, u.name, u.username
            ORDER BY total_points DESC, total_games DESC
        ";

        return DB::select($sql);
    }

    /**
     * Get total points for a specific user from result_english using native query
     * បន្ទោបពិន្ទុសរុបសម្រាប់អ្នកប្រើប្រាស់ជាក់លាក់ពីជួរ result_english ដោយប្រើ native query
     *
     * @param int $userId
     * @return object|null
     */
    public function getTotalPointsForUser($userId)
    {
        $sql = "
            SELECT
                u.id as user_id,
                u.name,
                u.username,
                COUNT(r.id) as total_games,
                COALESCE(SUM(
                    CASE
                        WHEN r.result_english REGEXP '^Prize [0-9]+$'
                        THEN CAST(REGEXP_REPLACE(r.result_english, '[^0-9]', '') AS UNSIGNED)
                        ELSE 0
                    END
                ), 0) as total_points,
                GROUP_CONCAT(
                    CONCAT(r.result_english, ' (', DATE_FORMAT(r.created_at, '%Y-%m-%d %H:%i'), ')')
                    SEPARATOR ', '
                ) as prize_history
            FROM users u
            LEFT JOIN results r ON u.id = r.user_id AND r.status = 'completed'
            WHERE u.id = ?
            GROUP BY u.id, u.name, u.username
        ";

        $result = DB::select($sql, [$userId]);
        return $result ? $result[0] : null;
    }

    /**
     * Get leaderboard with total points using native query
     * រកបញ្ជីអ្នកឈ្នះជាមួយពិន្ទុសរុបដោយប្រើ native query
     *
     * @param int $limit
     * @return array
     */
    public function getPointsLeaderboard($limit = 10)
    {
        $sql = "
            SELECT
                u.id as user_id,
                u.name,
                u.username,
                COUNT(r.id) as total_games,
                COALESCE(SUM(
                    CASE
                        WHEN r.result_english REGEXP '^Prize [0-9]+$'
                        THEN CAST(REGEXP_REPLACE(r.result_english, '[^0-9]', '') AS UNSIGNED)
                        ELSE 0
                    END
                ), 0) as total_points,
                MAX(r.created_at) as last_game_at
            FROM users u
            LEFT JOIN results r ON u.id = r.user_id AND r.status = 'completed'
            GROUP BY u.id, u.name, u.username
            HAVING total_points > 0
            ORDER BY total_points DESC, total_games DESC, last_game_at DESC
            LIMIT ?
        ";

        return DB::select($sql, [$limit]);
    }

    /**
     * Get daily points summary using native query
     * បន្ទោបសរុបពិន្ទុប្រចាំថ្ងៃដោយប្រើ native query
     *
     * @param string $date (Y-m-d format)
     * @return array
     */
    public function getDailyPointsSummary($date = null)
    {
        $date = $date ?: Carbon::today()->format('Y-m-d');

        $sql = "
            SELECT
                DATE(r.created_at) as game_date,
                COUNT(r.id) as total_games,
                COUNT(DISTINCT r.user_id) as unique_players,
                COALESCE(SUM(
                    CASE
                        WHEN r.result_english REGEXP '^Prize [0-9]+$'
                        THEN CAST(REGEXP_REPLACE(r.result_english, '[^0-9]', '') AS UNSIGNED)
                        ELSE 0
                    END
                ), 0) as total_points_awarded,
                AVG(
                    CASE
                        WHEN r.result_english REGEXP '^Prize [0-9]+$'
                        THEN CAST(REGEXP_REPLACE(r.result_english, '[^0-9]', '') AS UNSIGNED)
                        ELSE 0
                    END
                ) as avg_points_per_game
            FROM results r
            WHERE DATE(r.created_at) = ? AND r.status = 'completed'
            GROUP BY DATE(r.created_at)
        ";

        $result = DB::select($sql, [$date]);
        return $result ? $result[0] : null;
    }

    /**
     * Get points distribution using native query
     * បន្ទោបការចែកចាយពិន្ទុដោយប្រើ native query
     *
     * @return array
     */
    public function getPointsDistribution()
    {
        $sql = "
            SELECT
                r.result_english,
                CASE
                    WHEN r.result_english REGEXP '^Prize [0-9]+$'
                    THEN CAST(REGEXP_REPLACE(r.result_english, '[^0-9]', '') AS UNSIGNED)
                    ELSE 0
                END as points_value,
                COUNT(*) as frequency,
                ROUND((COUNT(*) * 100.0 / (SELECT COUNT(*) FROM results WHERE status = 'completed')), 2) as percentage
            FROM results r
            WHERE r.status = 'completed'
            GROUP BY r.result_english
            ORDER BY points_value DESC
        ";

        return DB::select($sql);
    }
}
