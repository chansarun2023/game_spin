<?php
namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Http;

class ReportController extends Controller
{
    protected $reportRepo;

    public function __construct()
    {
        $this->reportRepo = new \App\Http\Repositories\ReportRepositori();
    }

    /**
     * Get total points for all users
     * បន្ទោបពិន្ទុសរុបសម្រាប់អ្នកប្រើប្រាស់ទាំងអស់
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getAllUsersPoints(Request $request)
    {
        try {
            $data = $this->reportRepo->getTotalPointsForAllUsers();

            return response()->json([
                'success' => true,
                'message' => 'Total points retrieved successfully / បានទាញយកពិន្ទុសរុបដោយជោគជ័យ',
                'data' => $data,
                'count' => count($data)
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error fetching all users points: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve points data / បរាជ័យក្នុងការទាញយកទិន្នន័យពិន្ទុ',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get total points for a specific user
     * បន្ទោបពិន្ទុសរុបសម្រាប់អ្នកប្រើប្រាស់ជាក់លាក់
     *
     * @param Request $request
     * @param int $userId
     * @return \Illuminate\Http\JsonResponse
     */
    public function getUserPoints(Request $request, $userId)
    {
        try {
            $data = $this->reportRepo->getTotalPointsForUser($userId);

            if (!$data) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found / រកមិនឃើញអ្នកប្រើប្រាស់',
                    'data' => null
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'User points retrieved successfully / បានទាញយកពិន្ទុអ្នកប្រើប្រាស់ដោយជោគជ័យ',
                'data' => $data
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error fetching user points: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve user points / បរាជ័យក្នុងការទាញយកពិន្ទុអ្នកប្រើប្រាស់',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get current authenticated user's points
     * បន្ទោបពិន្ទុរបស់អ្នកប្រើប្រាស់បច្ចុប្បន្ន
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getMyPoints(Request $request)
    {
        try {
            $user = Auth::user();
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized / មិនមានសិទ្ធិចូលប្រើ',
                ], 401);
            }

            $data = $this->reportRepo->getTotalPointsForUser($user->id);

            return response()->json([
                'success' => true,
                'message' => 'Your points retrieved successfully / បានទាញយកពិន្ទុរបស់អ្នកដោយជោគជ័យ',
                'data' => $data
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error fetching my points: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve your points / បរាជ័យក្នុងការទាញយកពិន្ទុរបស់អ្នក',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get points leaderboard
     * បន្ទោបបញ្ជីអ្នកឈ្នះពិន្ទុ
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getLeaderboard(Request $request)
    {
        try {
            $limit = $request->get('limit', 10);

            $validator = Validator::make(['limit' => $limit], [
                'limit' => 'integer|min:1|max:100'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid limit parameter / ព័ត៌មានកំណត់ចំនួនមិនត្រឹមត្រូវ',
                    'errors' => $validator->errors()
                ], 400);
            }

            $data = $this->reportRepo->getPointsLeaderboard($limit);

            return response()->json([
                'success' => true,
                'message' => 'Leaderboard retrieved successfully / បានទាញយកបញ្ជីអ្នកឈ្នះដោយជោគជ័យ',
                'data' => $data,
                'count' => count($data),
                'limit' => $limit
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error fetching leaderboard: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve leaderboard / បរាជ័យក្នុងការទាញយកបញ្ជីអ្នកឈ្នះ',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get daily points summary
     * បន្ទោបសរុបពិន្ទុប្រចាំថ្ងៃ
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getDailySummary(Request $request)
    {
        try {
            $date = $request->get('date', null);

            if ($date) {
                $validator = Validator::make(['date' => $date], [
                    'date' => 'date_format:Y-m-d'
                ]);

                if ($validator->fails()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Invalid date format. Use Y-m-d / ទម្រង់កាលបរិច្ឆេទមិនត្រឹមត្រូវ ប្រើ Y-m-d',
                        'errors' => $validator->errors()
                    ], 400);
                }
            }

            $data = $this->reportRepo->getDailyPointsSummary($date);

            return response()->json([
                'success' => true,
                'message' => 'Daily summary retrieved successfully / បានទាញយកសរុបប្រចាំថ្ងៃដោយជោគជ័យ',
                'data' => $data,
                'date' => $date ?: date('Y-m-d')
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error fetching daily summary: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve daily summary / បរាជ័យក្នុងការទាញយកសរុបប្រចាំថ្ងៃ',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get points distribution
     * បន្ទោបការចែកចាយពិន្ទុ
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getPointsDistribution(Request $request)
    {
        try {
            $data = $this->reportRepo->getPointsDistribution();

            return response()->json([
                'success' => true,
                'message' => 'Points distribution retrieved successfully / បានទាញយកការចែកចាយពិន្ទុដោយជោគជ័យ',
                'data' => $data,
                'count' => count($data)
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error fetching points distribution: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve points distribution / បរាជ័យក្នុងការទាញយកការចែកចាយពិន្ទុ',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}

