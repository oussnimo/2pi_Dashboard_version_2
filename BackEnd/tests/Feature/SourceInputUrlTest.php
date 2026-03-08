<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SourceInputUrlTest extends TestCase
{
    use DatabaseTransactions;
    private function authUser(): User
    {
        return User::factory()->create();
    }

    // =========================================================
    //  1. Rejet URL invalide → 422
    // =========================================================

    public function test_rejects_invalid_url(): void
    {
        $user = $this->authUser();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/extract-url', [
                'url' => 'pas-une-url-valide',
            ]);

        $response->assertStatus(422);
    }

    // =========================================================
    //  2. Rejet URL vide → 422
    // =========================================================

    public function test_rejects_empty_url(): void
    {
        $user = $this->authUser();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/extract-url', []);

        $response->assertStatus(422);
    }

    // =========================================================
    //  3. Extraction depuis une page web (mocked)
    // =========================================================

    public function test_extracts_text_from_webpage(): void
    {
        $user = $this->authUser();

        $fakeHtml = '
        <html>
        <head><title>Test Page</title></head>
        <body>
            <nav>Navigation to ignore</nav>
            <article>
                <h1>Le Système Solaire</h1>
                <p>Le système solaire est composé de huit planètes qui orbitent autour du Soleil. 
                Mercure est la planète la plus proche du Soleil, suivie de Vénus, la Terre et Mars.
                Les planètes géantes sont Jupiter, Saturne, Uranus et Neptune.</p>
                <p>Jupiter est la plus grande planète du système solaire avec un diamètre de 142 984 km.
                Elle possède plus de 95 satellites naturels confirmés.</p>
            </article>
            <footer>Footer to ignore</footer>
        </body>
        </html>';

        Http::fake([
            'https://example.com/systeme-solaire' => Http::response($fakeHtml, 200),
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/extract-url', [
                'url' => 'https://example.com/systeme-solaire',
            ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['success', 'text', 'length', 'source'])
            ->assertJson([
                'success' => true,
                'source'  => 'webpage',
            ]);

        // Le texte extrait doit contenir le contenu de l'article
        $text = $response->json('text');
        $this->assertStringContainsString('système solaire', $text);
        $this->assertStringContainsString('planètes', $text);
        $this->assertGreaterThan(50, $response->json('length'));
    }

    // =========================================================
    //  4. Page web inaccessible → 422
    // =========================================================

    public function test_handles_inaccessible_webpage(): void
    {
        $user = $this->authUser();

        Http::fake([
            'https://example.com/not-found' => Http::response('Not Found', 404),
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/extract-url', [
                'url' => 'https://example.com/not-found',
            ]);

        $response->assertStatus(422)
            ->assertJson(['success' => false]);
    }

    // =========================================================
    //  5. Page web vide (sans contenu extractable) → 422
    // =========================================================

    public function test_handles_empty_webpage(): void
    {
        $user = $this->authUser();

        // Page avec seulement du JavaScript, pas de texte extractable
        $emptyHtml = '<html><head><title>App</title></head><body><script>console.log("app")</script></body></html>';

        Http::fake([
            'https://example.com/empty' => Http::response($emptyHtml, 200),
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/extract-url', [
                'url' => 'https://example.com/empty',
            ]);

        $response->assertStatus(422)
            ->assertJson(['success' => false]);
    }

    // =========================================================
    //  6. Détection YouTube — test unitaire via Reflection
    // =========================================================

    public function test_detects_youtube_urls_correctly(): void
    {
        $controller = new \App\Http\Controllers\SourceInputController();
        $method = new \ReflectionMethod($controller, 'extractYoutubeVideoId');
        $method->setAccessible(true);

        // URLs YouTube valides
        $youtubeUrls = [
            'https://www.youtube.com/watch?v=dQw4w9WgXcQ'      => 'dQw4w9WgXcQ',
            'https://youtu.be/dQw4w9WgXcQ'                     => 'dQw4w9WgXcQ',
            'https://www.youtube.com/embed/dQw4w9WgXcQ'         => 'dQw4w9WgXcQ',
            'https://www.youtube.com/shorts/dQw4w9WgXcQ'        => 'dQw4w9WgXcQ',
            'https://www.youtube.com/live/dQw4w9WgXcQ'          => 'dQw4w9WgXcQ',
            'https://www.youtube.com/watch?v=abc_DEF-123'       => 'abc_DEF-123',
        ];

        foreach ($youtubeUrls as $url => $expectedId) {
            $result = $method->invoke($controller, $url);
            $this->assertEquals($expectedId, $result, "Failed for URL: {$url}");
        }

        // URLs non-YouTube
        $nonYoutubeUrls = [
            'https://www.google.com',
            'https://example.com/video',
            'https://vimeo.com/12345',
        ];

        foreach ($nonYoutubeUrls as $url) {
            $result = $method->invoke($controller, $url);
            $this->assertNull($result, "Should return null for non-YouTube URL: {$url}");
        }
    }

    // =========================================================
    //  7. Non authentifié → 401
    // =========================================================

    public function test_unauthenticated_url_request_is_rejected(): void
    {
        $response = $this->postJson('/api/extract-url', [
            'url' => 'https://example.com',
        ]);

        $response->assertStatus(401);
    }
}
