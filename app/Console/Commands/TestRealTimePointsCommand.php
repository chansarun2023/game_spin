<?php

namespace App\Console\Commands;

use App\Events\PointsEarned;
use App\Events\LeaderboardUpdated;
use App\Models\User;
use App\Models\Result;
use App\Services\RealTimePointsService;
use Illuminate\Console\Command;

class TestRealTimePointsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:realtime-points
                            {--user-id= : Test with specific user ID}
                            {--points=25 : Points to award}
                            {--broadcast : Test broadcasting events}
                            {--leaderboard : Test leaderboard updates}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test real-time points functionality';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🧪 Testing Real-time Points System');
        $this->newLine();

        // Test broadcasting configuration
        $this->testBroadcastingConfig();

        // Test service functionality
        $this->testServiceFunctionality();

        // Test events
        if ($this->option('broadcast')) {
            $this->testBroadcastingEvents();
        }

        // Test leaderboard
        if ($this->option('leaderboard')) {
            $this->testLeaderboardUpdates();
        }

        $this->info('✅ Real-time points system test completed!');
    }

    /**
     * Test broadcasting configuration
     */
    private function testBroadcastingConfig()
    {
        $this->info('📡 Testing Broadcasting Configuration...');

        $driver = config('broadcasting.default');
        $this->line("Broadcast Driver: {$driver}");

        if ($driver === 'pusher') {
            $pusherConfig = config('broadcasting.connections.pusher');
            $this->line("Pusher App ID: " . ($pusherConfig['app_id'] ?: 'Not set'));
            $this->line("Pusher Key: " . ($pusherConfig['key'] ?: 'Not set'));
            $this->line("Pusher Cluster: " . ($pusherConfig['options']['cluster'] ?: 'Not set'));
        }

        $this->info('✅ Broadcasting configuration test completed');
        $this->newLine();
    }

    /**
     * Test service functionality
     */
    private function testServiceFunctionality()
    {
        $this->info('🔧 Testing Service Functionality...');

        $service = app(RealTimePointsService::class);

        // Test leaderboard
        $leaderboard = $service->getLeaderboard(5, 'all');
        $this->line("Leaderboard entries: " . count($leaderboard));

        // Test statistics
        $stats = $service->getRealTimeStats();
        $this->line("Total users with points: " . $stats['total_users_with_points']);
        $this->line("Total points in system: " . $stats['total_points_in_system']);

        // Test with specific user
        $userId = $this->option('user-id');
        if ($userId) {
            $user = User::find($userId);
            if ($user) {
                $userStatus = $service->getUserPointsStatus($user);
                $this->line("User {$user->username} current points: " . $userStatus['current_points']);
                $this->line("User rank: " . $userStatus['rank']);
            } else {
                $this->warn("User with ID {$userId} not found");
            }
        }

        $this->info('✅ Service functionality test completed');
        $this->newLine();
    }

    /**
     * Test broadcasting events
     */
    private function testBroadcastingEvents()
    {
        $this->info('📢 Testing Broadcasting Events...');

        $userId = $this->option('user-id');
        $points = (int) $this->option('points');

        if (!$userId) {
            // Get first user with points
            $user = User::where('points', '>', 0)->first();
            if (!$user) {
                $this->warn('No users with points found. Creating test user...');
                $user = User::factory()->create([
                    'username' => 'test_user_' . time(),
                    'points' => 100,
                ]);
            }
        } else {
            $user = User::find($userId);
            if (!$user) {
                $this->error("User with ID {$userId} not found");
                return;
            }
        }

        // Create a test result
        $result = Result::create([
            'user_id' => $user->id,
            'game_type' => 'spin_wheel',
            'game_code' => 'test_' . time(),
            'result_english' => "Points {$points}",
            'result_khmer' => "ពិន្ទុ {$points}",
            'result_color' => '#FF0000',
            'segment_index' => 1,
            'spin_angle' => 45,
            'status' => 'completed',
            'points_calculated' => false,
        ]);

        $this->line("Testing with user: {$user->username}");
        $this->line("Current points: {$user->getCurrentPoints()}");
        $this->line("Awarding {$points} points...");

        // Test PointsEarned event
        try {
            event(new PointsEarned($user, $result, $points, $user->getCurrentPoints() + $points));
            $this->info('✅ PointsEarned event broadcasted successfully');
        } catch (\Exception $e) {
            $this->error('❌ Failed to broadcast PointsEarned event: ' . $e->getMessage());
        }

        $this->info('✅ Broadcasting events test completed');
        $this->newLine();
    }

    /**
     * Test leaderboard updates
     */
    private function testLeaderboardUpdates()
    {
        $this->info('🏆 Testing Leaderboard Updates...');

        $service = app(RealTimePointsService::class);

        // Get current leaderboard
        $leaderboard = $service->getLeaderboard(5, 'all');
        $this->line("Current leaderboard entries: " . count($leaderboard));

        // Test LeaderboardUpdated event
        try {
            event(new LeaderboardUpdated($leaderboard));
            $this->info('✅ LeaderboardUpdated event broadcasted successfully');
        } catch (\Exception $e) {
            $this->error('❌ Failed to broadcast LeaderboardUpdated event: ' . $e->getMessage());
        }

        // Test service update method
        try {
            $service->updateLeaderboard();
            $this->info('✅ Leaderboard update method executed successfully');
        } catch (\Exception $e) {
            $this->error('❌ Failed to update leaderboard: ' . $e->getMessage());
        }

        $this->info('✅ Leaderboard updates test completed');
        $this->newLine();
    }
}
