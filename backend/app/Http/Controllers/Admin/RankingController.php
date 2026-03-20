<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Ranking\BuildAllTimeRankingService;

class RankingController extends Controller
{
    public function historical(BuildAllTimeRankingService $rankingService)
    {
        $historicalRanking = $rankingService->build();

        return view('admin.rankings.history', [
            'historicalRanking' => $historicalRanking,
            'minimumMatches' => 10,
        ]);
    }
}
