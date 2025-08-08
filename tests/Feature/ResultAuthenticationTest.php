<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Result;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

class ResultAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_store_result_with_user_id()
    {
        // Create a user
        $user = User::factory()->create([
            'username' => 'testuser',
            'email' => 'test@example.com',
            'status' => true,
            'role_id' => 6,
        ]);

        // Authenticate the user
        Sanctum::actingAs($user);

        // Make request to store result
        $response = $this->postJson('/api/v1/spin/result', [
            'segment_index' => 5,
            'result_english' => 'Test Prize',
            'result_khmer' => 'រង្វាន់តេស្ត',
            'result_color' => '#FF0000',
            'spin_angle' => 180.5,
            'game_code' => 'TEST123',
        ]);

        // Assert response
        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Spin result stored successfully',
            ]);

        // Assert result was stored with user_id
        $this->assertDatabaseHas('results', [
            'user_id' => $user->id,
            'game_type' => 'spin_wheel',
            'game_code' => 'TEST123',
            'result_english' => 'Test Prize',
            'result_khmer' => 'រង្វាន់តេស្ត',
        ]);
    }

    public function test_guest_user_can_store_result_without_user_id()
    {
        // Make request without authentication
        $response = $this->postJson('/api/v1/spin/result', [
            'segment_index' => 3,
            'result_english' => 'Guest Prize',
            'result_khmer' => 'រង្វាន់ភ្ញៀវ',
            'result_color' => '#00FF00',
            'spin_angle' => 90.0,
            'game_code' => 'GUEST123',
        ]);

        // Assert response
        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Spin result stored successfully',
            ]);

        // Assert result was stored without user_id
        $this->assertDatabaseHas('results', [
            'user_id' => null,
            'game_type' => 'spin_wheel',
            'game_code' => 'GUEST123',
            'result_english' => 'Guest Prize',
            'result_khmer' => 'រង្វាន់ភ្ញៀវ',
        ]);
    }

    public function test_authenticated_user_with_token_can_store_result()
    {
        // Create a user
        $user = User::factory()->create([
            'username' => 'tokenuser',
            'email' => 'token@example.com',
            'status' => true,
            'role_id' => 6,
        ]);

        // Create a token for the user using our custom method
        $token = $user->createTokenWithIdentifier('test-token', ['*'], [
            'device_type' => 'test',
            'device_info' => 'test-device',
            'session_id' => 'test-session',
        ])->plainTextToken;

        // Make request with Bearer token
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/v1/spin/result', [
                    'segment_index' => 7,
                    'result_english' => 'Token Prize',
                    'result_khmer' => 'រង្វាន់ថូខឹន',
                    'result_color' => '#0000FF',
                    'spin_angle' => 270.0,
                    'game_code' => 'TOKEN123',
                ]);

        // Assert response
        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Spin result stored successfully',
            ]);

        // Assert result was stored with user_id
        $this->assertDatabaseHas('results', [
            'user_id' => $user->id,
            'game_type' => 'spin_wheel',
            'game_code' => 'TOKEN123',
            'result_english' => 'Token Prize',
            'result_khmer' => 'រង្វាន់ថូខឹន',
        ]);
    }
}
