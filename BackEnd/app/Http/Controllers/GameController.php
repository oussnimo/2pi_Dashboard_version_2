<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Game;
use App\Models\Level;
use App\Models\BoxQuestionAnswer;
use App\Models\BalloonType;
use App\Models\BalloonAnswer;
use App\Models\Notification;
use Illuminate\Support\Facades\DB;

class GameController extends Controller
{
    public function storeGame(Request $request)
    {
        $request->validate([
            'course'     => 'required|string|max:255',
            'topic'      => 'required|string|max:255',
            'gameNumber' => 'required|integer',
            'numLevels'  => 'required|integer',
            'levels'     => 'required|array',
            'user_id'    => 'required|integer|exists:users,id',
            'game_id'    => 'nullable|integer|exists:games,game_id',
        ]);

        DB::beginTransaction();
        try {
            $gameId = $request->game_id;
            $userId = $request->user_id;
            $isUpdate = !is_null($gameId);

            if ($isUpdate) {
                $game = Game::where('game_id', $gameId)->where('user_id', $userId)->firstOrFail();
                $game->update([
                    'course'           => $request->course,
                    'topic'            => $request->topic,
                    'game_number'      => $request->gameNumber,
                    'number_of_levels' => $request->numLevels,
                ]);
                // Cascade delete handled by DB foreign keys (recommended)
                // or manually:
                Level::where('game_id', $game->game_id)->delete();
            } else {
                $game = Game::create([
                    'course'           => $request->course,
                    'topic'            => $request->topic,
                    'game_number'      => $request->gameNumber,
                    'number_of_levels' => $request->numLevels,
                    'user_id'          => $userId,
                ]);
            }

            // ✅ Batch insert levels
            $levelsToInsert = array_map(fn($ld) => [
                'game_id'      => $game->game_id,
                'level_number' => $ld['level_number'],
                'level_type'   => $ld['level_type'],
                'created_at'   => now(),
                'updated_at'   => now(),
            ], $request->levels);

            Level::insert($levelsToInsert);

            // Reload levels with IDs
            $savedLevels = Level::where('game_id', $game->game_id)
                ->orderBy('level_number')
                ->get()
                ->keyBy('level_number');

            $boxRows     = [];
            $balloonRows = [];

            foreach ($request->levels as $levelData) {
                $level = $savedLevels[$levelData['level_number']];

                if ($levelData['level_type'] === 'box' && !empty($levelData['questions'])) {
                    foreach ($levelData['questions'] as $q) {
                        $boxRows[] = [
                            'level_id'      => $level->level_id,
                            'question_text' => $q['text'],
                            'answer_text'   => $q['answer'],
                            'created_at'    => now(),
                            'updated_at'    => now(),
                        ];
                    }
                }

                if ($levelData['level_type'] === 'balloon' && !empty($levelData['question'])) {
                    $balloonRows[] = [
                        'level'   => $level,
                        'data'    => $levelData,
                    ];
                }
            }

            // ✅ Single insert for all box questions
            if (!empty($boxRows)) {
                BoxQuestionAnswer::insert($boxRows);
            }

            // Balloons need IDs so we insert one by one, but batch answers
            foreach ($balloonRows as $br) {
                $balloon = BalloonType::create([
                    'level_id'      => $br['level']->level_id,
                    'question_text' => $br['data']['question'],
                ]);

                if (!empty($br['data']['answers'])) {
                    $answerRows = array_map(fn($a) => [
                        'balloon_id'  => $balloon->id,
                        'answer_text' => $a['text'],
                        'is_correct'  => $a['is_true'],
                        'created_at'  => now(),
                        'updated_at'  => now(),
                    ], $br['data']['answers']);

                    BalloonAnswer::insert($answerRows);
                }
            }

            // ✅ Single notification
            Notification::create([
                'user_id' => $userId,
                'type'    => $isUpdate ? 'info' : 'success',
                'title'   => $isUpdate ? 'Game Updated' : 'New Game Created',
                'message' => $isUpdate
                    ? "Your game '{$request->topic}' has been updated."
                    : "New game '{$request->topic}' created successfully.",
                'read'    => false,
            ]);

            DB::commit();
            return response()->json([
                'message' => 'Game data stored successfully!',
                'game_id' => $game->game_id
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}