<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Http\Request;
use App\Http\Controllers\ResultController;
use App\Models\PersonalAccessToken;
use Illuminate\Support\Facades\Auth;

class TestSpinCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:spin {token} {--user-id=} {--segment=} {--angle=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test spin result storage with a specific token';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $token = $this->argument('token');
        $userId = $this->option('user-id');
        $segment = $this->option('segment') ?? rand(0, 14);
        $angle = $this->option('angle') ?? (rand(0, 360) + (rand(0, 99) / 100));

        $this->info("Testing spin with token: " . substr($token, 0, 10) . "...");

        // Find the token in the database
        $personalAccessToken = PersonalAccessToken::findToken($token);

        if (!$personalAccessToken) {
            $this->error("❌ Token not found in database");
            return 1;
        }

        $this->info("✅ Token found for user: " . $personalAccessToken->tokenable->username ?? 'Unknown');

        // Authenticate the user
        Auth::login($personalAccessToken->tokenable);

        // Create test spin data
        $spinData = [
            'segment_index' => $segment,
            'result_english' => 'Test Prize ' . rand(1, 100),
            'result_khmer' => 'រង្វាន់តេស្ត ' . rand(1, 100),
            'result_color' => '#' . str_pad(dechex(rand(0, 16777215)), 6, '0', STR_PAD_LEFT),
            'spin_angle' => $angle,
            'game_code' => 'TEST_' . strtoupper(substr(md5(rand()), 0, 8)),
        ];

        if ($userId) {
            $spinData['user_id'] = $userId;
        }

        $this->info("Spin data: " . json_encode($spinData, JSON_PRETTY_PRINT));

        // Create a mock request
        $request = new Request($spinData);
        $request->headers->set('Authorization', 'Bearer ' . $token);
        $request->headers->set('Content-Type', 'application/json');
        $request->headers->set('Accept', 'application/json');

        // Call the controller method
        $controller = new ResultController();

        try {
            $response = $controller->store($request);
            $responseData = json_decode($response->getContent(), true);

            $this->info("HTTP Status Code: " . $response->getStatusCode());

            if ($responseData) {
                $this->info("Response: " . json_encode($responseData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

                if (isset($responseData['success']) && $responseData['success']) {
                    $this->info("✅ Spin result stored successfully!");
                    if (isset($responseData['data']['id'])) {
                        $this->info("Result ID: " . $responseData['data']['id']);
                    }

                    // Check if user_id was stored correctly
                    if (isset($responseData['data']['id'])) {
                        $result = \App\Models\Result::find($responseData['data']['id']);
                        if ($result) {
                            $this->info("Stored user_id: " . ($result->user_id ?? 'null'));
                            if ($result->user_id) {
                                $this->info("✅ User ID correctly stored: " . $result->user_id);
                            } else {
                                $this->warn("⚠️ User ID is null - this might indicate an authentication issue");
                            }
                        }
                    }
                } else {
                    $this->error("❌ Failed to store spin result");
                    if (isset($responseData['message'])) {
                        $this->error("Error: " . $responseData['message']);
                    }
                }
            }

        } catch (\Exception $e) {
            $this->error("❌ Exception occurred: " . $e->getMessage());
            $this->error("File: " . $e->getFile() . ":" . $e->getLine());
            return 1;
        }

        $this->info(str_repeat("=", 50));
        $this->info("Test completed at: " . date('Y-m-d H:i:s'));

        return 0;
    }
}
