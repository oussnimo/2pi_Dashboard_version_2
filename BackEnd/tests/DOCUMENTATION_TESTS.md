# 📋 Documentation des Tests Backend — 2PI Dashboard

## Vue d'ensemble

**26 tests Feature** répartis dans **4 fichiers** pour valider le backend du système d'upload PDF/URL, extraction de texte, et génération de questions par l'IA.

> **Résultat** : ✅ 26/26 tests passent | ⏱️ Durée totale : ~10s

```
php artisan test --testsuite=Feature
```

---

## Architecture testée

```
Utilisateur
    ↓
┌────────────────────────────────────┐
│  POST /api/extract-file            │  ← SourceInputFileTest
│  (PDF, TXT, DOCX → texte extrait) │
└────────────────────────────────────┘
    ↓
┌────────────────────────────────────┐
│  POST /api/extract-url             │  ← SourceInputUrlTest
│  (URL/YouTube → texte extrait)     │
└────────────────────────────────────┘
    ↓
┌────────────────────────────────────┐
│  POST /api/generate-questions      │  ← AIQuestionGenerationTest
│  (texte + prompt → questions IA)   │
└────────────────────────────────────┘
    ↓
┌────────────────────────────────────┐
│  Mesure des performances           │  ← PerformanceTest
│  (timing upload → réponse)         │
└────────────────────────────────────┘
```

---

## 1️⃣ SourceInputFileTest.php

**Route testée** : `POST /api/extract-file` (auth: Sanctum)
**Controller** : `SourceInputController@extractFromFile`

| # | Test | Description | Résultat attendu |
|---|------|-------------|------------------|
| 1 | `test_extract_text_from_valid_pdf` | Upload un PDF valide et vérifie l'extraction du texte | Status 200, `success: true`, texte non vide, `processing_time` présent |
| 2 | `test_extract_text_from_valid_txt` | Upload un fichier .txt avec du contenu en français | Status 200, texte contient "système solaire" et "Mercure" |
| 3 | `test_minimum_delay_is_respected` | Vérifie que le délai artificiel de 1.5s est appliqué | Temps ≥ 1.4s, `processing_time` ≥ 1.5s |
| 4 | `test_rejects_empty_file` | Upload un fichier .txt vide | Status 422 (erreur de validation) |
| 5 | `test_rejects_invalid_file_type` | Upload un fichier .jpg (type non supporté) | Status 422 |
| 6 | `test_rejects_request_without_file` | Envoie une requête sans fichier | Status 422 |
| 7 | `test_unauthenticated_request_is_rejected` | Requête sans token Sanctum | Status 401 |

**Détail important** : Le test #3 vérifie que le `SourceInputController` ajoute bien un `usleep()` pour garantir un temps minimum de 1.5 seconde — ceci évite que le frontend affiche un spinner qui disparaît trop vite.

---

## 2️⃣ SourceInputUrlTest.php

**Route testée** : `POST /api/extract-url` (auth: Sanctum)
**Controller** : `SourceInputController@extractFromUrl`

| # | Test | Description | Résultat attendu |
|---|------|-------------|------------------|
| 1 | `test_rejects_invalid_url` | Envoie "pas-une-url-valide" | Status 422 |
| 2 | `test_rejects_empty_url` | Requête sans URL | Status 422 |
| 3 | `test_extracts_text_from_webpage` | Page HTML mockée avec `<article>` sur le système solaire | Status 200, `source: "webpage"`, texte contient "planètes" |
| 4 | `test_handles_inaccessible_webpage` | URL retourne 404 (mocked) | Status 422, `success: false` |
| 5 | `test_handles_empty_webpage` | Page avec seulement du `<script>`, pas de texte | Status 422, `success: false` |
| 6 | `test_detects_youtube_urls_correctly` | Test unitaire via Reflection de `extractYoutubeVideoId()` | Détecte correctement 6 formats YouTube, retourne `null` pour les non-YouTube |
| 7 | `test_unauthenticated_url_request_is_rejected` | Requête sans token | Status 401 |

**Formats YouTube testés** :
- `youtube.com/watch?v=ID`
- `youtu.be/ID`
- `youtube.com/embed/ID`
- `youtube.com/shorts/ID`
- `youtube.com/live/ID`
- IDs avec caractères spéciaux (`abc_DEF-123`)

---

## 3️⃣ AIQuestionGenerationTest.php

**Route testée** : `POST /api/generate-questions` (auth: Sanctum)
**Controller** : `AIQuestionController@generateQuestions`

| # | Test | Description | Résultat attendu |
|---|------|-------------|------------------|
| 1 | `test_generates_questions_with_two_levels` | 2 niveaux : box (5 questions) + balloon (1 question, 10 réponses) | Status 200, structure JSON correcte, box=5 questions, balloon=10 answers |
| 2 | `test_generates_questions_with_source_text` | Génération avec texte source (contenu PDF) | Status 200, vérifie que "TEXTE SOURCE" est envoyé à l'API Groq |
| 3 | `test_rejects_missing_required_fields` | Requête vide | Status 422 |
| 4 | `test_rejects_missing_course` | Sans le champ `course` | Status 422, erreur sur `course` |
| 5 | `test_rejects_missing_ai_prompt` | Sans le champ `ai_prompt` | Status 422, erreur sur `ai_prompt` |
| 6 | `test_rejects_mismatched_levels_count` | `numLevels=3` mais `level_types=['box']` (1 seul) | Status 422 |
| 7 | `test_rejects_invalid_level_type` | `level_types=['invalid_type']` | Status 422 |
| 8 | `test_uses_mock_data_when_api_key_missing` | `GROQ_API_KEY` supprimée → fallback mock | Status 200, questions contiennent "MOCK" |
| 9 | `test_unauthenticated_request_is_rejected` | Sans token Sanctum | Status 401 |
| 10 | `test_response_time_is_acceptable` | Mesure le temps avec mock API | Temps < 2 secondes |

**Comment fonctionne le mock de l'API Groq** :
```php
Http::fake([
    'https://api.groq.com/openai/v1/chat/completions' => Http::response([
        'choices' => [['message' => ['content' => '{"levels": [...]}']]],
    ], 200),
]);
```
→ Aucun appel HTTP réel n'est fait. La réponse est simulée instantanément.

---

## 4️⃣ PerformanceTest.php

**Routes testées** : `/api/extract-file` et `/api/generate-questions`

| # | Test | Description | Seuil |
|---|------|-------------|-------|
| 1 | `test_file_extraction_completes_within_time_limit` | Extraction d'un fichier TXT (50 phrases) | ≥ 1.4s (délai) et < 5s |
| 2 | `test_ai_generation_with_mock_is_fast` | Génération 1 niveau box avec mock Groq | < 2s |
| 3 | `test_ai_generation_with_source_text_performance` | Génération 2 niveaux avec gros texte source | < 3s |
| 4 | `test_mock_fallback_is_instant` | Fallback mock (sans API key) | < 0.5s |

**Résultats mesurés** :
```
Extraction fichier : 1.62s  (✅ entre 1.5s et 5s)
Génération mock    : 0.07s  (✅ < 2s)
Avec source_text   : 0.07s  (✅ < 3s)
Fallback instant   : 0.07s  (✅ < 0.5s)
```

---

## 🔧 Configuration technique

### Base de données
- Les tests utilisent **`DatabaseTransactions`** (pas `RefreshDatabase`)
- Chaque test crée un utilisateur temporaire via `User::factory()->create()`
- À la fin du test, un **rollback automatique** est effectué
- **Tes données MySQL ne sont jamais modifiées** ✅

### Mocking HTTP
- `Http::fake()` intercepte les appels à `api.groq.com`
- `Http::assertSent()` vérifie que les bonnes données sont envoyées
- Aucune requête externe n'est faite pendant les tests

### Authentification
- `$this->actingAs($user, 'sanctum')` simule un utilisateur connecté
- Les tests d'auth vérifient que les routes protégées retournent 401 sans token

---

## 📌 Commandes

```bash
# Lancer tous les tests Feature
php artisan test --testsuite=Feature

# Lancer un fichier de test spécifique
php artisan test --filter=SourceInputFileTest
php artisan test --filter=SourceInputUrlTest
php artisan test --filter=AIQuestionGenerationTest
php artisan test --filter=PerformanceTest

# Lancer un test spécifique
php artisan test --filter=test_minimum_delay_is_respected
php artisan test --filter=test_generates_questions_with_source_text

# Lancer avec verbose (plus de détails)
php artisan test --testsuite=Feature -v
```

---

## 📂 Fichiers concernés

| Fichier de test | Controller testé | Route |
|----------------|------------------|-------|
| `tests/Feature/SourceInputFileTest.php` | `SourceInputController` | `POST /api/extract-file` |
| `tests/Feature/SourceInputUrlTest.php` | `SourceInputController` | `POST /api/extract-url` |
| `tests/Feature/AIQuestionGenerationTest.php` | `AIQuestionController` | `POST /api/generate-questions` |
| `tests/Feature/PerformanceTest.php` | Les deux controllers | Les deux routes |

---

*Documentation générée le 05/03/2026 — 26 tests, 102 assertions, tous validés ✅*
