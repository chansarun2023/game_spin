<?php

namespace App\Http\Controllers;

use App\Models\AgentKey;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class AgentKeyController extends Controller
{
    /**
     * Get all active agent keys
     *
     * @return JsonResponse
     */
    public function index(): JsonResponse
    {
        try {
            $agentKeys = AgentKey::active()->get();

            return response()->json([
                'success' => true,
                'data' => $agentKeys
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch agent keys'
            ], 500);
        }
    }

    /**
     * Create a new agent key
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'agent_host' => 'required|string|max:255',
                'expires_at' => 'nullable|date|after:now',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $agentKey = AgentKey::create([
                'agent_key' => AgentKey::generateUniqueKey(),
                'name' => $request->name,
                'agent_host' => $request->agent_host,
                'status' => true,
                'expires_at' => $request->expires_at ?? now()->addDays(30),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Agent key created successfully',
                'data' => $agentKey
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create agent key'
            ], 500);
        }
    }

    /**
     * Validate an agent key
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function validateKey(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'agent_key' => 'required|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $agentKey = AgentKey::where('agent_key', $request->agent_key)
                ->where('status', true)
                ->first();

            if (!$agentKey) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid or inactive agent key'
                ], 404);
            }

            if ($agentKey->isExpired()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Agent key has expired'
                ], 400);
            }

            return response()->json([
                'success' => true,
                'message' => 'Agent key is valid',
                'data' => [
                    'name' => $agentKey->name,
                    'host' => $agentKey->agent_host,
                    'expires_at' => $agentKey->expires_at
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to validate agent key'
            ], 500);
        }
    }

    /**
     * Deactivate an agent key
     *
     * @param string $agentKey
     * @return JsonResponse
     */
    public function deactivate(string $agentKey): JsonResponse
    {
        try {
            $key = AgentKey::where('agent_key', $agentKey)->first();

            if (!$key) {
                return response()->json([
                    'success' => false,
                    'message' => 'Agent key not found'
                ], 404);
            }

            $key->update(['status' => false]);

            return response()->json([
                'success' => true,
                'message' => 'Agent key deactivated successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to deactivate agent key'
            ], 500);
        }
    }
}
