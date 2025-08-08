<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Product;
use App\Models\Reward;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;

class RewardsClaimTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected User $user;
    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a test user with points
        $this->user = User::factory()->create([
            'points' => 1000,
            'total_points' => 1500,
        ]);

        // Create a test product
        $this->product = Product::create([
            'name' => 'Test Gaming Voucher',
            'code' => 'TEST_GAMING_VOUCHER',
            'description' => 'Test gaming voucher for testing',
            'point_cost' => 500,
            'icon' => 'gamepad',
            'is_active' => true,
            'stock' => 10,
        ]);
    }

    /** @test */
    public function user_can_claim_reward_with_sufficient_points()
    {
        $response = $this->postJson('/api/v1/rewards/claim', [
            'user_id' => $this->user->id,
            'product_id' => $this->product->id,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Reward claimed successfully',
            ])
            ->assertJsonStructure([
                'success',
                'data' => [
                    'reward_id',
                    'product_name',
                    'points_spent',
                    'remaining_points',
                    'claimed_at',
                    'expires_at',
                ],
                'message',
            ]);

        // Verify the reward was created
        $this->assertDatabaseHas('rewards', [
            'user_id' => $this->user->id,
            'product_id' => $this->product->id,
            'points_spent' => 500,
            'status' => 'claimed',
        ]);

        // Verify user points were deducted
        $this->user->refresh();
        $this->assertEquals(500, $this->user->points); // 1000 - 500 = 500

        // Verify product stock was decreased
        $this->product->refresh();
        $this->assertEquals(9, $this->product->stock); // 10 - 1 = 9
    }

    /** @test */
    public function user_cannot_claim_reward_with_insufficient_points()
    {
        // Set user points below product cost
        $this->user->update(['points' => 300]);

        $response = $this->postJson('/api/v1/rewards/claim', [
            'user_id' => $this->user->id,
            'product_id' => $this->product->id,
        ]);

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'Insufficient points to claim this reward',
                'data' => [
                    'required_points' => 500,
                    'user_points' => 300,
                ],
            ]);

        // Verify no reward was created
        $this->assertDatabaseMissing('rewards', [
            'user_id' => $this->user->id,
            'product_id' => $this->product->id,
        ]);

        // Verify user points were not deducted
        $this->user->refresh();
        $this->assertEquals(300, $this->user->points);

        // Verify product stock was not decreased
        $this->product->refresh();
        $this->assertEquals(10, $this->product->stock);
    }

    /** @test */
    public function user_cannot_claim_inactive_product()
    {
        // Deactivate the product
        $this->product->update(['is_active' => false]);

        $response = $this->postJson('/api/v1/rewards/claim', [
            'user_id' => $this->user->id,
            'product_id' => $this->product->id,
        ]);

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'This reward is not available',
            ]);

        // Verify no reward was created
        $this->assertDatabaseMissing('rewards', [
            'user_id' => $this->user->id,
            'product_id' => $this->product->id,
        ]);
    }

    /** @test */
    public function user_cannot_claim_out_of_stock_product()
    {
        // Set product stock to 0
        $this->product->update(['stock' => 0]);

        $response = $this->postJson('/api/v1/rewards/claim', [
            'user_id' => $this->user->id,
            'product_id' => $this->product->id,
        ]);

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'This reward is out of stock',
            ]);

        // Verify no reward was created
        $this->assertDatabaseMissing('rewards', [
            'user_id' => $this->user->id,
            'product_id' => $this->product->id,
        ]);
    }

    /** @test */
    public function user_can_claim_unlimited_stock_product()
    {
        // Set product to unlimited stock
        $this->product->update(['stock' => -1]);

        $response = $this->postJson('/api/v1/rewards/claim', [
            'user_id' => $this->user->id,
            'product_id' => $this->product->id,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Reward claimed successfully',
            ]);

        // Verify the reward was created
        $this->assertDatabaseHas('rewards', [
            'user_id' => $this->user->id,
            'product_id' => $this->product->id,
            'points_spent' => 500,
            'status' => 'claimed',
        ]);

        // Verify product stock remains unlimited
        $this->product->refresh();
        $this->assertEquals(-1, $this->product->stock);
    }

    /** @test */
    public function claim_requires_valid_user_id()
    {
        $response = $this->postJson('/api/v1/rewards/claim', [
            'user_id' => 99999, // Non-existent user
            'product_id' => $this->product->id,
        ]);

        $response->assertStatus(422); // Validation error
    }

    /** @test */
    public function claim_requires_valid_product_id()
    {
        $response = $this->postJson('/api/v1/rewards/claim', [
            'user_id' => $this->user->id,
            'product_id' => 99999, // Non-existent product
        ]);

        $response->assertStatus(422); // Validation error
    }

    /** @test */
    public function claim_requires_user_id_parameter()
    {
        $response = $this->postJson('/api/v1/rewards/claim', [
            'product_id' => $this->product->id,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['user_id']);
    }

    /** @test */
    public function claim_requires_product_id_parameter()
    {
        $response = $this->postJson('/api/v1/rewards/claim', [
            'user_id' => $this->user->id,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['product_id']);
    }

    /** @test */
    public function reward_expires_after_30_days()
    {
        $response = $this->postJson('/api/v1/rewards/claim', [
            'user_id' => $this->user->id,
            'product_id' => $this->product->id,
        ]);

        $response->assertStatus(200);

        $reward = Reward::where('user_id', $this->user->id)
            ->where('product_id', $this->product->id)
            ->first();

        $this->assertNotNull($reward);
        $this->assertEquals(
            now()->addDays(30)->format('Y-m-d'),
            $reward->expires_at->format('Y-m-d')
        );
    }

    /** @test */
    public function multiple_users_can_claim_same_product()
    {
        $user2 = User::factory()->create(['points' => 1000]);

        // First user claims
        $response1 = $this->postJson('/api/v1/rewards/claim', [
            'user_id' => $this->user->id,
            'product_id' => $this->product->id,
        ]);

        // Second user claims
        $response2 = $this->postJson('/api/v1/rewards/claim', [
            'user_id' => $user2->id,
            'product_id' => $this->product->id,
        ]);

        $response1->assertStatus(200);
        $response2->assertStatus(200);

        // Verify both rewards were created
        $this->assertDatabaseHas('rewards', [
            'user_id' => $this->user->id,
            'product_id' => $this->product->id,
        ]);

        $this->assertDatabaseHas('rewards', [
            'user_id' => $user2->id,
            'product_id' => $this->product->id,
        ]);

        // Verify stock was decreased twice
        $this->product->refresh();
        $this->assertEquals(8, $this->product->stock); // 10 - 2 = 8
    }

    /** @test */
    public function user_can_claim_multiple_different_products()
    {
        $product2 = Product::create([
            'name' => 'Test Music Subscription',
            'code' => 'TEST_MUSIC_SUBSCRIPTION',
            'description' => 'Test music subscription for testing',
            'point_cost' => 300,
            'icon' => 'music-note',
            'is_active' => true,
            'stock' => 5,
        ]);

        // Claim first product
        $response1 = $this->postJson('/api/v1/rewards/claim', [
            'user_id' => $this->user->id,
            'product_id' => $this->product->id,
        ]);

        // Claim second product
        $response2 = $this->postJson('/api/v1/rewards/claim', [
            'user_id' => $this->user->id,
            'product_id' => $product2->id,
        ]);

        $response1->assertStatus(200);
        $response2->assertStatus(200);

        // Verify both rewards were created
        $this->assertDatabaseHas('rewards', [
            'user_id' => $this->user->id,
            'product_id' => $this->product->id,
        ]);

        $this->assertDatabaseHas('rewards', [
            'user_id' => $this->user->id,
            'product_id' => $product2->id,
        ]);

        // Verify user points were deducted for both
        $this->user->refresh();
        $this->assertEquals(200, $this->user->points); // 1000 - 500 - 300 = 200
    }

    /** @test */
    public function database_transaction_rolls_back_on_failure()
    {
        // Mock the deductPoints method to fail
        $this->user->shouldReceive('deductPoints')->andReturn(false);

        $response = $this->postJson('/api/v1/rewards/claim', [
            'user_id' => $this->user->id,
            'product_id' => $this->product->id,
        ]);

        $response->assertStatus(500);

        // Verify no reward was created
        $this->assertDatabaseMissing('rewards', [
            'user_id' => $this->user->id,
            'product_id' => $this->product->id,
        ]);

        // Verify product stock was not decreased
        $this->product->refresh();
        $this->assertEquals(10, $this->product->stock);
    }

    /** @test */
    public function response_includes_correct_remaining_points()
    {
        $response = $this->postJson('/api/v1/rewards/claim', [
            'user_id' => $this->user->id,
            'product_id' => $this->product->id,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'remaining_points' => 500, // 1000 - 500
                ],
            ]);
    }

    /** @test */
    public function response_includes_correct_product_name()
    {
        $response = $this->postJson('/api/v1/rewards/claim', [
            'user_id' => $this->user->id,
            'product_id' => $this->product->id,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'product_name' => 'Test Gaming Voucher',
                ],
            ]);
    }
}
