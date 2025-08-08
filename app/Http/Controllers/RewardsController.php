<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Product;
use App\Models\Reward;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class RewardsController extends Controller
{
    /**
     * Get available rewards
     */
    public function getAvailableRewards(): JsonResponse
    {
        $rewards = Product::active()
            ->inStock()
            ->orderBy('point_cost', 'asc')
            ->get()
            ->map(function ($product) {
                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'description' => $product->description,
                    'point_cost' => $product->point_cost,
                    'icon' => $product->icon,
                    'stock' => $product->stock,
                    'code' => $product->code,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $rewards,
            'message' => 'Available rewards retrieved successfully',
        ]);
    }

    /**
     * Claim a reward
     */
    public function claimReward(Request $request): JsonResponse
    {
        $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'product_id' => 'required|integer|exists:products,id',
        ]);

        $user = User::findOrFail($request->user_id);
        $product = Product::findOrFail($request->product_id);

        // Check if product is active and in stock
        if (!$product->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'This reward is not available',
            ], 400);
        }

        if (!$product->isInStock()) {
            return response()->json([
                'success' => false,
                'message' => 'This reward is out of stock',
            ], 400);
        }

        // Check if user has enough points
        if (!$user->hasEnoughPoints($product->point_cost)) {
            return response()->json([
                'success' => false,
                'message' => 'Insufficient points to claim this reward',
                'data' => [
                    'required_points' => $product->point_cost,
                    'user_points' => $user->getCurrentPoints(),
                ],
            ], 400);
        }

        try {
            DB::beginTransaction();

            // Deduct points from user
            if (!$user->deductPoints($product->point_cost)) {
                throw new \Exception('Failed to deduct points');
            }

            // Decrease product stock
            if (!$product->decreaseStock()) {
                throw new \Exception('Failed to decrease stock');
            }

            // Create reward record
            $reward = Reward::create([
                'user_id' => $user->id,
                'product_id' => $product->id,
                'points_spent' => $product->point_cost,
                'status' => 'claimed',
                'claimed_at' => now(),
                'expires_at' => now()->addDays(30), // Rewards expire in 30 days
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => [
                    'reward_id' => $reward->id,
                    'product_name' => $product->name,
                    'points_spent' => $product->point_cost,
                    'remaining_points' => $user->getCurrentPoints(),
                    'claimed_at' => $reward->claimed_at,
                    'expires_at' => $reward->expires_at,
                ],
                'message' => 'Reward claimed successfully',
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to claim reward: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get user's recent rewards
     */
    public function getRecentRewards(Request $request): JsonResponse
    {


        $limit = $request->get('limit', 10);
        $user = User::findOrFail($request->user_id);

        $rewards = $user->rewards()
            ->with('product')
            ->orderBy('claimed_at', 'desc')
            ->limit($limit)
            ->get()
            ->map(function ($reward) {
                return [
                    'id' => $reward->id,
                    'product_name' => $reward->product->name,
                    'product_description' => $reward->product->description,
                    'points_spent' => $reward->points_spent,
                    'status' => $reward->status,
                    'claimed_at' => $reward->claimed_at,
                    'expires_at' => $reward->expires_at,
                    'used_at' => $reward->used_at,
                    'icon' => $reward->product->icon,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $rewards,
            'message' => 'Recent rewards retrieved successfully',
        ]);
    }

    /**
     * Get user's reward history
     */
    public function getRewardHistory(Request $request): JsonResponse
    {
        $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'status' => 'string|in:claimed,used,expired',
            'page' => 'integer|min:1',
            'per_page' => 'integer|min:1|max:100',
        ]);

        $user = User::findOrFail($request->user_id);
        $perPage = $request->get('per_page', 20);
        $status = $request->get('status');

        $query = $user->rewards()->with('product');

        if ($status) {
            $query->where('status', $status);
        }

        $rewards = $query->orderBy('claimed_at', 'desc')
            ->paginate($perPage);

        $rewards->getCollection()->transform(function ($reward) {
            return [
                'id' => $reward->id,
                'product_name' => $reward->product->name,
                'product_description' => $reward->product->description,
                'points_spent' => $reward->points_spent,
                'status' => $reward->status,
                'claimed_at' => $reward->claimed_at,
                'expires_at' => $reward->expires_at,
                'used_at' => $reward->used_at,
                'icon' => $reward->product->icon,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $rewards,
            'message' => 'Reward history retrieved successfully',
        ]);
    }

    /**
     * Use a reward
     */
    public function useReward(Request $request): JsonResponse
    {
        $request->validate([
            'reward_id' => 'required|integer|exists:rewards,id',
        ]);

        $reward = Reward::with('user', 'product')->findOrFail($request->reward_id);

        // Check if reward is claimed and not expired
        if ($reward->status !== 'claimed') {
            return response()->json([
                'success' => false,
                'message' => 'Reward cannot be used. Status: ' . $reward->status,
            ], 400);
        }

        if ($reward->expires_at && $reward->expires_at->isPast()) {
            $reward->update(['status' => 'expired']);

            return response()->json([
                'success' => false,
                'message' => 'Reward has expired',
            ], 400);
        }

        // Mark reward as used
        $reward->update([
            'status' => 'used',
            'used_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'reward_id' => $reward->id,
                'product_name' => $reward->product->name,
                'used_at' => $reward->used_at,
            ],
            'message' => 'Reward used successfully',
        ]);
    }
}
