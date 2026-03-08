<?php

use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\GameController;
use App\Http\Controllers\RemoveGameController;
use App\Http\Controllers\GetGamesController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\AIQuestionController;
use App\Http\Controllers\SourceInputController;
use App\Http\Controllers\ExportController;

// ─── Public Routes ────────────────────────────────────────────────
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login',    [AuthController::class, 'login']);
Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('/reset-password',  [AuthController::class, 'resetPassword']);

// ─── Protected Routes ─────────────────────────────────────────────
Route::middleware('auth:sanctum')->group(function () {

    // Auth
    Route::post('/logout',  [AuthController::class, 'logout']);
    Route::get('/user',     [AuthController::class, 'user']);
    Route::put('/profile',  [AuthController::class, 'updateProfile']);

    // Games ✅ now all protected
    Route::post('/game',        [GameController::class, 'storeGame']);
    Route::get('/select',       [GetGamesController::class, 'getGames']);
    Route::get('/lastGames',    [GetGamesController::class, 'getLastCreatedGames']);
    Route::get('/getGameById',  [GetGamesController::class, 'getGameById']);
    Route::delete('/delete',    [RemoveGameController::class, 'deleteGame']);

    // AI & Source
    Route::post('/generate-questions', [AIQuestionController::class, 'generateQuestions']);
    Route::post('/extract-file',       [SourceInputController::class, 'extractFromFile']);
    Route::post('/extract-url',        [SourceInputController::class, 'extractFromUrl']);

    // Notifications
    Route::get('/notifications',                    [NotificationController::class, 'getNotifications']);
    Route::post('/notifications',                   [NotificationController::class, 'create']);
    Route::put('/notifications/{id}/read',          [NotificationController::class, 'markAsRead']);
    Route::put('/notifications/read-all',           [NotificationController::class, 'markAllAsRead']);
    Route::delete('/notifications/{id}',            [NotificationController::class, 'delete']);
    Route::delete('/notifications',                 [NotificationController::class, 'deleteAll']);
});

// ─── Export (Public — SCORM) ──────────────────────────────────────
// ⚠️ Si tu veux le protéger aussi, déplace dans le groupe auth:sanctum
Route::post('/export-quiz-zip', [ExportController::class, 'exportQuizAsZip']);

