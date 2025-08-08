<?php

use App\Http\Controllers\frontend\AuthController;
use App\Http\Controllers\ResultController;
use App\Http\Controllers\AgentKeyController;
use App\Http\Controllers\PointsController;
use App\Http\Controllers\RewardsController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\RealTimePointsController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::group(['prefix' => 'auth'], function () {
        Route::post('login', [AuthController::class, 'login']);
        Route::post('login-game', [AuthController::class, 'loginGame']);
        Route::post('register', [AuthController::class, 'loginGame']);
        Route::post('validate-token', [AuthController::class, 'validateToken']);
        Route::post('check-session', [AuthController::class, 'checkSession']);

        // Debug routes (for development only)
        Route::post('debug-tokens', [AuthController::class, 'debugTokens']);
        Route::post('cleanup-tokens', [AuthController::class, 'cleanupTokens']);

        // Protected routes requiring authentication
        Route::middleware('auth:sanctum')->group(function () {
            Route::post('logout', [AuthController::class, 'logout']);
            Route::post('logout-all', [AuthController::class, 'logoutAll']);
            Route::get('tokens', [AuthController::class, 'getUserTokens']);
            Route::delete('tokens/{identifier}', [AuthController::class, 'revokeToken']);
        });
    });

    // Spin wheel routes (accessible to both authenticated and guest users)
    Route::group(['prefix' => 'spin'], function () {
        // Routes with optional authentication (works for both authenticated and guest users)
        Route::middleware('auth.optional')->group(function () {
            Route::post('result', [ResultController::class, 'store']); // Save spin result
            Route::get('results', [ResultController::class, 'index']); // Get spin history
            Route::get('statistics', [ResultController::class, 'statistics']); // Get statistics
        });

        // Public routes (accessible to guests)
        Route::get('result/{identifier}', [ResultController::class, 'show']); // Get specific result
    });

    // Points calculation routes
    Route::group(['prefix' => 'points'], function () {
        Route::get('calculate', [ResultController::class, 'calculatePoints']); // Calculate points for all users
        Route::get('user/{userId}', [ResultController::class, 'calculateUserPoints']); // Calculate points for specific user
    });

    // Agent key management routes
    Route::group(['prefix' => 'agent'], function () {
        Route::get('keys', [AgentKeyController::class, 'index']); // Get all active agent keys
        Route::post('keys', [AgentKeyController::class, 'store']); // Create new agent key
        Route::post('keys/validate', [AgentKeyController::class, 'validateKey']); // Validate agent key
        Route::delete('keys/{agentKey}', [AgentKeyController::class, 'deactivate']); // Deactivate agent key
    });

    // Points management routes
    Route::group(['prefix' => 'points'], function () {
        Route::get('user/{userId}', [PointsController::class, 'getUserPoints']); // Get user points
        Route::post('calculate/{userId}', [PointsController::class, 'calculateUserPoints']); // Calculate points for specific user
        Route::post('calculate-all', [PointsController::class, 'calculateAllUsersPoints']); // Calculate points for all users
    });

    // Rewards management routes
    Route::group(['prefix' => 'rewards'], function () {
        Route::get('available', [RewardsController::class, 'getAvailableRewards']); // Get available rewards
        Route::post('claim', [RewardsController::class, 'claimReward']); // Claim a reward
        Route::get('recent/{user_id?}', [RewardsController::class, 'getRecentRewards']); // Get recent rewards with optional user_id parameter
        Route::get('history', [RewardsController::class, 'getRewardHistory']); // Get reward history
        Route::post('use', [RewardsController::class, 'useReward']); // Use a reward
    });

    // Product management routes
    Route::group(['prefix' => 'products'], function () {
        Route::get('/', [ProductController::class, 'index']); // Get all products (with filters)
        Route::get('/active', [ProductController::class, 'active']); // Get active products only
        Route::get('/by-point-range', [ProductController::class, 'byPointRange']); // Get products by point range
        Route::get('/{id}', [ProductController::class, 'show']); // Get specific product
        Route::post('/', [ProductController::class, 'store']); // Create new product
        Route::put('/{id}', [ProductController::class, 'update']); // Update product
        Route::delete('/{id}', [ProductController::class, 'destroy']); // Delete product
        Route::post('/bulk-update', [ProductController::class, 'bulkUpdate']); // Bulk update products
    });

    // Real-time points routes
    Route::group(['prefix' => 'realtime'], function () {
        Route::get('leaderboard', [RealTimePointsController::class, 'getLeaderboard']); // Get real-time leaderboard
        Route::get('stats', [RealTimePointsController::class, 'getRealTimeStats']); // Get real-time statistics
        Route::get('data', [RealTimePointsController::class, 'getRealTimeData']); // Get comprehensive real-time data
        Route::get('broadcasting-info', [RealTimePointsController::class, 'getBroadcastingInfo']); // Get broadcasting configuration

        // Protected routes requiring authentication
        Route::middleware('auth:sanctum')->group(function () {
            Route::get('user-points/{userId?}', [RealTimePointsController::class, 'getUserPointsStatus']); // Get user's real-time points status with optional dynamic user ID
            Route::post('force-update-leaderboard', [RealTimePointsController::class, 'forceUpdateLeaderboard']); // Force update leaderboard (admin only)
        });
    });
});
