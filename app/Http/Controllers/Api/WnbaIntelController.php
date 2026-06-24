<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Services\WNBA\Data\PlayerIntelService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WnbaIntelController extends Controller
{
    use ApiResponseTrait;

    public function news(Request $request, PlayerIntelService $intelService): JsonResponse
    {
        $request->validate([
            'limit' => 'nullable|integer|min:1|max:50',
            'filter' => 'nullable|string|in:top,recent,fantasy',
        ]);

        try {
            $data = $intelService->leagueNews(
                $request->input('limit') ? (int) $request->input('limit') : null,
                $request->input('filter'),
            );

            return $this->successResponse($data, 'League news retrieved successfully');
        } catch (\Exception $e) {
            return $this->handleException($e, 'Retrieving league news');
        }
    }

    public function injuries(PlayerIntelService $intelService): JsonResponse
    {
        try {
            $data = $intelService->leagueInjuries();

            return $this->successResponse($data, 'League injuries retrieved successfully');
        } catch (\Exception $e) {
            return $this->handleException($e, 'Retrieving league injuries');
        }
    }
}
