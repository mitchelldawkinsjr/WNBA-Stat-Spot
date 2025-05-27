<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ScanPlayerProps;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class PropScannerController extends Controller
{
    /**
     * Scan player props for a game
     */
    public function scan(Request $request)
    {
        try {
            // Validate request
            $validator = Validator::make($request->all(), [
                'game_id' => 'required|integer',
                'props' => 'required|array',
                'props.*.player_id' => 'required|integer',
                'props.*.stat_type' => 'required|string',
                'props.*.line_value' => 'required|numeric',
                'props.*.odds_over' => 'nullable|numeric',
                'props.*.odds_under' => 'nullable|numeric'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'error' => 'Validation failed',
                    'messages' => $validator->errors()
                ], 422);
            }

            $gameId = $request->input('game_id');
            $props = $request->input('props');

            // Check cache first
            $cacheKey = "prop_scan:{$gameId}";
            if (Cache::has($cacheKey)) {
                return response()->json([
                    'status' => 'success',
                    'source' => 'cache',
                    'data' => Cache::get($cacheKey)
                ]);
            }

            // Dispatch job
            ScanPlayerProps::dispatch($gameId, $props);

            return response()->json([
                'status' => 'success',
                'message' => 'Prop scan job dispatched',
                'job_id' => $gameId
            ]);

        } catch (\Exception $e) {
            Log::error('Prop scan request failed', [
                'error' => $e->getMessage(),
                'request' => $request->all()
            ]);

            return response()->json([
                'error' => 'An error occurred while processing your request',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get scan results
     */
    public function getResults(int $gameId)
    {
        try {
            $cacheKey = "prop_scan:{$gameId}";

            if (!Cache::has($cacheKey)) {
                return response()->json([
                    'status' => 'pending',
                    'message' => 'Scan results not yet available'
                ], 202);
            }

            return response()->json([
                'status' => 'success',
                'source' => 'cache',
                'data' => Cache::get($cacheKey)
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get scan results', [
                'game_id' => $gameId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'error' => 'An error occurred while retrieving scan results',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get scan status
     */
    public function getStatus(int $gameId)
    {
        try {
            $cacheKey = "prop_scan:{$gameId}";

            return response()->json([
                'status' => Cache::has($cacheKey) ? 'completed' : 'pending',
                'game_id' => $gameId
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get scan status', [
                'game_id' => $gameId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'error' => 'An error occurred while checking scan status',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
