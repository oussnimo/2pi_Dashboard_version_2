<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Game;

class GetGamesController extends Controller
{
    // ✅ Get all games for a user — no transaction needed for reads
    public function getGames(Request $request)
    {
        $request->validate([
            'user_id' => 'required|integer|exists:users,id',
        ]);

        $games = Game::where('user_id', $request->user_id)->get();

        return response()->json([
            'message' => 'Games selected successfully!',
            'data' => $games
        ], 200);
    }

    // ✅ Get last 6 created games — no transaction, clean map
    public function getLastCreatedGames(Request $request, $limit = 6)
    {
        $request->validate([
            'user_id' => 'required|integer|exists:users,id',
        ]);

        $games = Game::where('user_id', $request->user_id)
            ->orderBy('created_at', 'desc')
            ->take($limit)
            ->get(['game_id', 'course', 'topic', 'game_number', 'number_of_levels', 'created_at']);

        $formatted = $games->map(fn($game) => [
            'quiz_id'         => $game->game_id,
            'timestamp'       => $game->created_at,
            'title'           => "{$game->course} - {$game->topic} - Game #{$game->game_number}",
            'number_of_levels'=> $game->number_of_levels,
        ]);

        return response()->json([
            'message' => 'Last created games fetched successfully!',
            'data' => $formatted
        ], 200);
    }

    // ✅ Get game by ID — Eager Loading fixes N+1 queries
    public function getGameById(Request $request)
    {
        $data = $request->validate([
            'game_id' => 'required|integer|exists:games,game_id',
            'user_id' => 'required|integer|exists:users,id',
        ]);

        // ✅ Single query with all relations — no more N+1 !
        $game = Game::where('game_id', $data['game_id'])
            ->where('user_id', $data['user_id'])
            ->with([
                'levels.boxQuestionAnswer',
                'levels.balloonType.answers',
            ])
            ->first();

        if (!$game) {
            return response()->json([
                'message' => 'Game and user relation validation failed.',
            ], 400);
        }

        $levels = $game->levels->map(function ($level) {
            $levelData = [
                'level_number' => $level->level_number,
                'level_type'   => $level->level_type,
                'level_stats'  => [
                    'coins'      => 0,
                    'lifes'      => 5,
                    'mistakes'   => 0,
                    'stars'      => 1,
                    'time_spent' => 0,
                ],
            ];

            if ($level->level_type === 'box') {
                $levelData['questions'] = $level->boxQuestionAnswer->map(fn($q) => [
                    'text'   => $q->question_text,
                    'answer' => $q->answer_text,
                ])->toArray();
            } elseif ($level->level_type === 'balloon') {
                $balloon = $level->balloonType->first(); // hasMany mais on prend le premier
                $levelData['question'] = $balloon?->question_text;
                $levelData['answers']  = $balloon
                    ? $balloon->answers->map(fn($a) => [
                        'text'    => $a->answer_text,
                        'is_true' => $a->is_correct,
                    ])->toArray()
                    : [];
            }

            return $levelData;
        })->toArray();

        return response()->json([
            'course'      => $game->course,
            'topic'       => $game->topic,
            'gameNumber'  => $game->game_number,
            'numLevels'   => $game->number_of_levels,
            'levels'      => $levels,
            'player_info' => ['current_level' => 1, 'lives' => 3, 'score' => 0],
        ], 200);
    }
}
