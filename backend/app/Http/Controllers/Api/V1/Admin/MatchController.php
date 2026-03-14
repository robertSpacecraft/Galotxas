<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\GameMatch;
use Illuminate\Http\Request;

class MatchController extends Controller
{
    public function validateResult(Request $request, GameMatch $gameMatch)
    {
        $gameMatch->update([
            'status' => 'validated',
            'validated_by' => $request->user()->id,
        ]);

        return response()->json($gameMatch);
    }
}
