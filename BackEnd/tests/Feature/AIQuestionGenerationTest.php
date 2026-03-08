<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AIQuestionGenerationTest extends TestCase
{
    use DatabaseTransactions;
    private function authUser(): User
    {
        return User::factory()->create();
    }

    /**
     * Fake Groq API response with valid JSON structure.
     */
    private function fakeGroqResponse(array $levels): void
    {
        $responseJson = json_encode(['levels' => $levels]);

        Http::fake([
            'https://api.groq.com/openai/v1/chat/completions' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => $responseJson,
                        ],
                    ],
                ],
            ], 200),
        ]);
    }

    /**
     * Génère un level "box" avec 5 questions pour les mocks.
     */
    private function makeBoxLevel(int $levelNumber): array
    {
        $questions = [];
        for ($i = 1; $i <= 5; $i++) {
            $questions[] = [
                'text'   => "Question {$i} du niveau {$levelNumber} ?",
                'answer' => "Réponse {$i}",
            ];
        }

        return [
            'level_number' => $levelNumber,
            'level_type'   => 'box',
            'level_stats'  => ['coins' => 0, 'lifes' => 5, 'mistakes' => 0, 'stars' => 1, 'time_spent' => 0],
            'questions'    => $questions,
        ];
    }

    /**
     * Génère un level "balloon" avec 1 question + 10 réponses pour les mocks.
     */
    private function makeBalloonLevel(int $levelNumber): array
    {
        $answers = [];
        for ($i = 1; $i <= 10; $i++) {
            $answers[] = [
                'text'    => "Option {$i}",
                'is_true' => $i <= 3, // 3 vraies, 7 fausses
            ];
        }

        return [
            'level_number' => $levelNumber,
            'level_type'   => 'balloon',
            'level_stats'  => ['coins' => 0, 'lifes' => 5, 'mistakes' => 0, 'stars' => 1, 'time_spent' => 0],
            'question'     => "Question balloon du niveau {$levelNumber} ?",
            'answers'      => $answers,
        ];
    }

    // =========================================================
    //  1. Génération réussie avec 2 niveaux (box + balloon)
    // =========================================================

    public function test_generates_questions_with_two_levels(): void
    {
        $user = $this->authUser();

        $this->fakeGroqResponse([
            $this->makeBoxLevel(1),
            $this->makeBalloonLevel(2),
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/generate-questions', [
                'course'      => 'Mathématiques',
                'topic'       => 'Les fractions',
                'gameNumber'  => 1,
                'numLevels'   => 2,
                'level_types' => ['box', 'balloon'],
                'ai_prompt'   => 'Génère des questions sur les fractions pour des élèves de CM2',
            ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'success',
                'data' => [
                    'course',
                    'topic',
                    'gameNumber',
                    'numLevels',
                    'levels',
                    'player_info' => ['current_level', 'lives', 'score'],
                ],
            ]);

        $data = $response->json('data');

        // Vérifie le nombre de niveaux
        $this->assertCount(2, $data['levels']);

        // Vérifie niveau 1 (box): 5 questions
        $level1 = $data['levels'][0];
        $this->assertEquals('box', $level1['level_type']);
        $this->assertCount(5, $level1['questions']);

        // Vérifie niveau 2 (balloon): 1 question + 10 réponses
        $level2 = $data['levels'][1];
        $this->assertEquals('balloon', $level2['level_type']);
        $this->assertNotEmpty($level2['question']);
        $this->assertCount(10, $level2['answers']);
    }

    // =========================================================
    //  2. Génération avec source_text (contenu PDF)
    // =========================================================

    public function test_generates_questions_with_source_text(): void
    {
        $user = $this->authUser();

        $sourceText = "LE SYSTÈME SOLAIRE\n\nMercure - 58 millions km du Soleil.\nVénus - 108 millions km.\nTerre - 150 millions km, 1 satellite (Lune).";

        $this->fakeGroqResponse([
            $this->makeBoxLevel(1),
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/generate-questions', [
                'course'      => 'Sciences',
                'topic'       => 'Système Solaire',
                'gameNumber'  => 1,
                'numLevels'   => 1,
                'level_types' => ['box'],
                'ai_prompt'   => 'Questions sur les planètes basées sur le PDF',
                'source_text' => $sourceText,
            ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        // Vérifie que la requête envoyée à Groq contenait le source_text
        Http::assertSent(function ($request) use ($sourceText) {
            $body = $request->body();
            // Le source_text doit être dans le prompt envoyé à l'API
            return str_contains($body, 'TEXTE SOURCE');
        });
    }

    // =========================================================
    //  3. Validation: champs requis manquants → 422
    // =========================================================

    public function test_rejects_missing_required_fields(): void
    {
        $user = $this->authUser();

        // Sans aucun champ
        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/generate-questions', []);

        $response->assertStatus(422);
    }

    public function test_rejects_missing_course(): void
    {
        $user = $this->authUser();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/generate-questions', [
                'topic'       => 'Test',
                'gameNumber'  => 1,
                'numLevels'   => 1,
                'level_types' => ['box'],
                'ai_prompt'   => 'Test prompt',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('course');
    }

    public function test_rejects_missing_ai_prompt(): void
    {
        $user = $this->authUser();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/generate-questions', [
                'course'      => 'Math',
                'topic'       => 'Test',
                'gameNumber'  => 1,
                'numLevels'   => 1,
                'level_types' => ['box'],
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('ai_prompt');
    }

    // =========================================================
    //  4. Incohérence numLevels vs level_types → 422
    // =========================================================

    public function test_rejects_mismatched_levels_count(): void
    {
        $user = $this->authUser();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/generate-questions', [
                'course'      => 'Math',
                'topic'       => 'Test',
                'gameNumber'  => 1,
                'numLevels'   => 3,       // dit 3 niveaux
                'level_types' => ['box'], // mais seulement 1 type
                'ai_prompt'   => 'Test prompt',
            ]);

        $response->assertStatus(422);
    }

    // =========================================================
    //  5. Type de level invalide → 422
    // =========================================================

    public function test_rejects_invalid_level_type(): void
    {
        $user = $this->authUser();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/generate-questions', [
                'course'      => 'Math',
                'topic'       => 'Test',
                'gameNumber'  => 1,
                'numLevels'   => 1,
                'level_types' => ['invalid_type'],
                'ai_prompt'   => 'Test prompt',
            ]);

        $response->assertStatus(422);
    }

    // =========================================================
    //  6. Fallback mock quand GROQ_API_KEY manquante
    // =========================================================

    public function test_uses_mock_data_when_api_key_missing(): void
    {
        $user = $this->authUser();

        // Supprime la clé API pour forcer le fallback
        config(['services.groq.api_key' => null]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/generate-questions', [
                'course'      => 'Sciences',
                'topic'       => 'Test',
                'gameNumber'  => 1,
                'numLevels'   => 2,
                'level_types' => ['box', 'balloon'],
                'ai_prompt'   => 'Questions de test',
            ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        // Les données mockées contiennent "MOCK" dans les questions
        $data = $response->json('data');
        $this->assertCount(2, $data['levels']);
        $this->assertStringContainsString('MOCK', $data['levels'][0]['questions'][0]['text']);
    }

    // =========================================================
    //  7. Non authentifié → 401
    // =========================================================

    public function test_unauthenticated_request_is_rejected(): void
    {
        $response = $this->postJson('/api/generate-questions', [
            'course'      => 'Math',
            'topic'       => 'Test',
            'gameNumber'  => 1,
            'numLevels'   => 1,
            'level_types' => ['box'],
            'ai_prompt'   => 'Test',
        ]);

        $response->assertStatus(401);
    }

    // =========================================================
    //  8. Mesure du temps de réponse (avec mock API)
    // =========================================================

    public function test_response_time_is_acceptable(): void
    {
        $user = $this->authUser();

        $this->fakeGroqResponse([
            $this->makeBoxLevel(1),
        ]);

        $startTime = microtime(true);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/generate-questions', [
                'course'      => 'Math',
                'topic'       => 'Addition',
                'gameNumber'  => 1,
                'numLevels'   => 1,
                'level_types' => ['box'],
                'ai_prompt'   => 'Questions simples',
            ]);

        $elapsed = microtime(true) - $startTime;

        $response->assertStatus(200);

        // Avec une API mockée (réponse instantanée), le traitement devrait être < 2s
        $this->assertLessThan(2.0, $elapsed, "La génération avec mock API devrait prendre < 2s. Temps mesuré: {$elapsed}s");
    }
}
