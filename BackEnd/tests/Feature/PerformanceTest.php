<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PerformanceTest extends TestCase
{
    use DatabaseTransactions;
    private function authUser(): User
    {
        return User::factory()->create();
    }

    // =========================================================
    //  1. Temps d'extraction fichier TXT < 5s (hors délai artificiel)
    // =========================================================

    public function test_file_extraction_completes_within_time_limit(): void
    {
        $user = $this->authUser();

        $content = str_repeat("Le système solaire contient 8 planètes. ", 50);
        $txtPath = tempnam(sys_get_temp_dir(), 'perf_') . '.txt';
        file_put_contents($txtPath, $content);

        $startTime = microtime(true);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/extract-file', [
                'file' => new UploadedFile($txtPath, 'performance_test.txt', 'text/plain', null, true),
            ]);

        $elapsed = microtime(true) - $startTime;

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        // Total devrait être < 5s (inclut le délai artificiel de 1.5s)
        $this->assertLessThan(5.0, $elapsed,
            "L'extraction de fichier devrait se terminer en < 5s. Temps mesuré: {$elapsed}s"
        );

        // Mais >= 1.4s (délai artificiel minimum)
        $this->assertGreaterThanOrEqual(1.4, $elapsed,
            "Le délai minimum de 1.5s devrait être respecté. Temps mesuré: {$elapsed}s"
        );

        @unlink($txtPath);
    }

    // =========================================================
    //  2. Temps de génération IA (mock) < 2s
    // =========================================================

    public function test_ai_generation_with_mock_is_fast(): void
    {
        $user = $this->authUser();

        $mockLevels = [
            [
                'level_number' => 1,
                'level_type'   => 'box',
                'level_stats'  => ['coins' => 0, 'lifes' => 5, 'mistakes' => 0, 'stars' => 1, 'time_spent' => 0],
                'questions'    => [
                    ['text' => 'Q1?', 'answer' => 'A1'],
                    ['text' => 'Q2?', 'answer' => 'A2'],
                    ['text' => 'Q3?', 'answer' => 'A3'],
                    ['text' => 'Q4?', 'answer' => 'A4'],
                    ['text' => 'Q5?', 'answer' => 'A5'],
                ],
            ],
        ];

        Http::fake([
            'https://api.groq.com/openai/v1/chat/completions' => Http::response([
                'choices' => [
                    ['message' => ['content' => json_encode(['levels' => $mockLevels])]],
                ],
            ], 200),
        ]);

        $startTime = microtime(true);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/generate-questions', [
                'course'      => 'Math',
                'topic'       => 'Addition',
                'gameNumber'  => 1,
                'numLevels'   => 1,
                'level_types' => ['box'],
                'ai_prompt'   => 'Simple questions',
            ]);

        $elapsed = microtime(true) - $startTime;

        $response->assertStatus(200)->assertJson(['success' => true]);

        $this->assertLessThan(2.0, $elapsed,
            "Avec mock API, la génération devrait prendre < 2s. Temps: {$elapsed}s"
        );
    }

    // =========================================================
    //  3. Temps de génération avec source_text
    // =========================================================

    public function test_ai_generation_with_source_text_performance(): void
    {
        $user = $this->authUser();

        // Simule un gros texte source (comme un PDF extrait)
        $sourceText = str_repeat("Les fractions sont des nombres rationnels. 1/2 = 0.5. ", 100);

        $mockLevels = [
            [
                'level_number' => 1,
                'level_type'   => 'box',
                'level_stats'  => ['coins' => 0, 'lifes' => 5, 'mistakes' => 0, 'stars' => 1, 'time_spent' => 0],
                'questions'    => [
                    ['text' => 'Q1?', 'answer' => 'A1'],
                    ['text' => 'Q2?', 'answer' => 'A2'],
                    ['text' => 'Q3?', 'answer' => 'A3'],
                    ['text' => 'Q4?', 'answer' => 'A4'],
                    ['text' => 'Q5?', 'answer' => 'A5'],
                ],
            ],
            [
                'level_number' => 2,
                'level_type'   => 'balloon',
                'level_stats'  => ['coins' => 0, 'lifes' => 5, 'mistakes' => 0, 'stars' => 1, 'time_spent' => 0],
                'question'     => 'Question balloon?',
                'answers'      => array_map(fn($i) => ['text' => "Opt{$i}", 'is_true' => $i <= 3], range(1, 10)),
            ],
        ];

        Http::fake([
            'https://api.groq.com/openai/v1/chat/completions' => Http::response([
                'choices' => [
                    ['message' => ['content' => json_encode(['levels' => $mockLevels])]],
                ],
            ], 200),
        ]);

        $startTime = microtime(true);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/generate-questions', [
                'course'      => 'Math',
                'topic'       => 'Fractions',
                'gameNumber'  => 1,
                'numLevels'   => 2,
                'level_types' => ['box', 'balloon'],
                'ai_prompt'   => 'Questions sur les fractions basées sur le texte source',
                'source_text' => $sourceText,
            ]);

        $elapsed = microtime(true) - $startTime;

        $response->assertStatus(200)->assertJson(['success' => true]);

        // Même avec un gros source_text, le mock devrait être rapide
        $this->assertLessThan(3.0, $elapsed,
            "Génération avec source_text et mock API devrait prendre < 3s. Temps: {$elapsed}s"
        );

        // Vérifie que source_text a été envoyé à l'API
        Http::assertSent(function ($request) {
            return str_contains($request->body(), 'TEXTE SOURCE');
        });
    }

    // =========================================================
    //  4. Comparaison temps sans délai (mock fallback)
    // =========================================================

    public function test_mock_fallback_is_instant(): void
    {
        $user = $this->authUser();

        // Sans clé API → utilise getMockBulkData() instantanément
        config(['services.groq.api_key' => null]);

        $startTime = microtime(true);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/generate-questions', [
                'course'      => 'Sciences',
                'topic'       => 'Test',
                'gameNumber'  => 1,
                'numLevels'   => 1,
                'level_types' => ['box'],
                'ai_prompt'   => 'Test rapide',
            ]);

        $elapsed = microtime(true) - $startTime;

        $response->assertStatus(200)->assertJson(['success' => true]);

        // Le fallback mock devrait être quasi-instantané (< 0.5s)
        $this->assertLessThan(0.5, $elapsed,
            "Le fallback mock devrait être quasi-instantané (< 0.5s). Temps: {$elapsed}s"
        );
    }
}
