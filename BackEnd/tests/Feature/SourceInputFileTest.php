<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class SourceInputFileTest extends TestCase
{
    use DatabaseTransactions;
    /**
     * Helper: create an authenticated user for Sanctum-protected routes.
     */
    private function authUser(): User
    {
        return User::factory()->create();
    }

    // =========================================================
    //  1. Upload PDF valide → extraction de texte
    // =========================================================

    public function test_extract_text_from_valid_pdf(): void
    {
        $user = $this->authUser();

        // Cherche un PDF existant dans storage/app/pdfs/ ou utilise un PDF du projet
        $pdfPath = $this->findOrCreateTestPdf();

        if ($pdfPath === null) {
            $this->markTestSkipped('Aucun fichier PDF de test disponible. Placez un PDF dans storage/app/pdfs/ pour tester.');
        }

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/extract-file', [
                'file' => new UploadedFile($pdfPath, 'test.pdf', 'application/pdf', null, true),
            ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'text',
                'length',
                'processing_time',
            ])
            ->assertJson(['success' => true]);

        // le texte extrait ne doit pas être vide
        $this->assertNotEmpty($response->json('text'));
        $this->assertGreaterThan(0, $response->json('length'));

        // processing_time doit être présent
        $this->assertNotNull($response->json('processing_time'));

        // Nettoyage du temp file si on l'a créé
        if (str_starts_with($pdfPath, sys_get_temp_dir())) {
            @unlink($pdfPath);
        }
    }

    // =========================================================
    //  2. Upload TXT valide → extraction de texte
    // =========================================================

    public function test_extract_text_from_valid_txt(): void
    {
        $user = $this->authUser();

        $content = "Le système solaire est composé de 8 planètes. Mercure est la plus proche du Soleil.";
        $txtPath = tempnam(sys_get_temp_dir(), 'test_') . '.txt';
        file_put_contents($txtPath, $content);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/extract-file', [
                'file' => new UploadedFile($txtPath, 'cours.txt', 'text/plain', null, true),
            ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        // Le texte extrait doit contenir le contenu original
        $this->assertStringContainsString('système solaire', $response->json('text'));
        $this->assertStringContainsString('Mercure', $response->json('text'));

        @unlink($txtPath);
    }

    // =========================================================
    //  3. Délai minimum de 1.5 secondes respecté
    // =========================================================

    public function test_minimum_delay_is_respected(): void
    {
        $user = $this->authUser();

        $content = "Contenu simple pour test de délai.";
        $txtPath = tempnam(sys_get_temp_dir(), 'test_') . '.txt';
        file_put_contents($txtPath, $content);

        $startTime = microtime(true);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/extract-file', [
                'file' => new UploadedFile($txtPath, 'delay_test.txt', 'text/plain', null, true),
            ]);

        $elapsed = microtime(true) - $startTime;

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        // Le SourceInputController a un délai minimum de 1.5s
        $this->assertGreaterThanOrEqual(1.4, $elapsed, "Le temps de réponse devrait être >= 1.5s (délai artificiel). Temps mesuré: {$elapsed}s");

        // Le processing_time renvoyé doit aussi refléter ce délai
        $processingTime = (float) rtrim($response->json('processing_time'), 's');
        $this->assertGreaterThanOrEqual(1.4, $processingTime, "Le processing_time renvoyé devrait être >= 1.5s");

        @unlink($txtPath);
    }

    // =========================================================
    //  4. Rejet fichier vide → 422
    // =========================================================

    public function test_rejects_empty_file(): void
    {
        $user = $this->authUser();

        $txtPath = tempnam(sys_get_temp_dir(), 'test_') . '.txt';
        file_put_contents($txtPath, '');

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/extract-file', [
                'file' => new UploadedFile($txtPath, 'empty.txt', 'text/plain', null, true),
            ]);

        // Un fichier vide devrait retourner une erreur 422
        $response->assertStatus(422);

        @unlink($txtPath);
    }

    // =========================================================
    //  5. Rejet type de fichier invalide → 422
    // =========================================================

    public function test_rejects_invalid_file_type(): void
    {
        $user = $this->authUser();

        $jpgPath = tempnam(sys_get_temp_dir(), 'test_') . '.jpg';
        file_put_contents($jpgPath, 'fake image data');

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/extract-file', [
                'file' => new UploadedFile($jpgPath, 'image.jpg', 'image/jpeg', null, true),
            ]);

        $response->assertStatus(422);

        @unlink($jpgPath);
    }

    // =========================================================
    //  6. Requête sans fichier → 422
    // =========================================================

    public function test_rejects_request_without_file(): void
    {
        $user = $this->authUser();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/extract-file', []);

        $response->assertStatus(422);
    }

    // =========================================================
    //  7. Non authentifié → 401
    // =========================================================

    public function test_unauthenticated_request_is_rejected(): void
    {
        $txtPath = tempnam(sys_get_temp_dir(), 'test_') . '.txt';
        file_put_contents($txtPath, 'test content');

        $response = $this->postJson('/api/extract-file', [
            'file' => new UploadedFile($txtPath, 'test.txt', 'text/plain', null, true),
        ]);

        $response->assertStatus(401);

        @unlink($txtPath);
    }

    // =========================================================
    //  HELPER: Crée un PDF minimal valide avec smalot/pdfparser
    // =========================================================

    private function findOrCreateTestPdf(): ?string
    {
        // 1. Cherche un PDF existant dans storage/app/pdfs/
        $pdfsDir = storage_path('app/pdfs');
        if (is_dir($pdfsDir)) {
            $files = glob($pdfsDir . '/*.pdf');
            if (!empty($files)) {
                return $files[0]; // Utilise le premier PDF trouvé
            }
        }

        // 2. Cherche dans le dossier public/
        $publicDir = public_path();
        $publicPdfs = glob($publicDir . '/*.pdf');
        if (!empty($publicPdfs)) {
            return $publicPdfs[0];
        }

        // 3. Crée un PDF minimal via la librairie smalot/pdfparser-compatible
        //    On utilise une approche qui crée un PDF valide que pdfparser peut lire
        try {
            $tempPath = tempnam(sys_get_temp_dir(), 'test_pdf_') . '.pdf';
            
            // PDF minimal avec texte encodé correctement
            $content = "BT /F1 12 Tf 72 720 Td (Test PDF Content for Unit Testing) Tj ET";
            $contentLength = strlen($content);
            
            $pdf = "%PDF-1.4\n";
            $offsets = [];
            
            $offsets[1] = strlen($pdf);
            $pdf .= "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n";
            
            $offsets[2] = strlen($pdf);
            $pdf .= "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n";
            
            $offsets[3] = strlen($pdf);
            $pdf .= "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>\nendobj\n";
            
            $offsets[4] = strlen($pdf);
            $pdf .= "4 0 obj\n<< /Length {$contentLength} >>\nstream\n{$content}\nendstream\nendobj\n";
            
            $offsets[5] = strlen($pdf);
            $pdf .= "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n";
            
            $xrefOffset = strlen($pdf);
            $pdf .= "xref\n0 6\n";
            $pdf .= "0000000000 65535 f \n";
            for ($i = 1; $i <= 5; $i++) {
                $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
            }
            
            $pdf .= "trailer\n<< /Size 6 /Root 1 0 R >>\n";
            $pdf .= "startxref\n{$xrefOffset}\n%%EOF";
            
            file_put_contents($tempPath, $pdf);
            return $tempPath;
        } catch (\Exception $e) {
            return null;
        }
    }
}
