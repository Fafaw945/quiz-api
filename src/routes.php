<?php
// Fichier : src/routes.php - Version avec correction des caractères invisibles

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

// Fonction utilitaire pour le formatage JSON (obligatoire pour Slim 4)
function setJsonResponse(Response $response, array $data, int $status = 200): Response {
    $response = $response->withHeader('Content-Type', 'application/json')->withStatus($status);
    $response->getBody()->write(json_encode($data));
    return $response;
}


// ===============================
// 1. Inscription
// ===============================
$app->post('/api/register', function (Request $request, Response $response) use ($pdo) {
    $data = json_decode($request->getBody()->getContents(), true);
    $name = trim($data['name'] ?? '');
    $pseudo = trim($data['pseudo'] ?? '');
    $email = trim($data['email'] ?? '');
    $password = trim($data['password'] ?? '');

    if (!$name || !$pseudo || !$email || !$password) {
        return setJsonResponse($response, ['error' => 'Champs manquants.'], 400);
    }

    $stmt = $pdo->prepare("SELECT id FROM participants WHERE email = ? OR pseudo = ?");
    $stmt->execute([$email, $pseudo]);
    if ($stmt->fetch()) {
        return setJsonResponse($response, ['error' => 'Cet email ou pseudo est déjà utilisé.'], 409);
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);
    $isAdmin = ($email === 'admin@quiz.com') ? 1 : 0;

    // Réinitialisation de 'is_ready' et 'game_started' à 0 pour tout nouvel inscrit
    $stmt = $pdo->prepare(
        "INSERT INTO participants (name, pseudo, email, password, score, is_admin, is_ready, game_started) 
         VALUES (?, ?, ?, ?, 0, ?, 0, 0)"
    );
    $stmt->execute([$name, $pseudo, $email, $hash, $isAdmin]);
    $id = (int) $pdo->lastInsertId();

    if ($id <= 0) {
        error_log("ERREUR CRITIQUE: lastInsertId() a retourné un ID invalide: {$id}");
        return setJsonResponse($response, ['error' => 'Erreur lors de l’enregistrement de l’utilisateur. ID invalide.'], 500);
    }
    
    return setJsonResponse($response, ['message' => 'Inscription réussie', 'id' => $id, 'is_admin' => $isAdmin], 201);
});

// ===============================
// 2. Connexion
// ===============================
$app->post('/api/login', function (Request $request, Response $response) use ($pdo) {
    $data = json_decode($request->getBody()->getContents(), true);
    $email = trim($data['email'] ?? '');
    $password = trim($data['password'] ?? '');

    if (!$email || !$password) {
        return setJsonResponse($response, ['error' => 'Email ou mot de passe manquant.'], 400);
    }

    $stmt = $pdo->prepare("SELECT id, password, name, pseudo, is_admin FROM participants WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user || !password_verify($password, $user['password'])) {
        return setJsonResponse($response, ['error' => 'Identifiants invalides.'], 401);
    }

    return setJsonResponse($response, [
        'message' => 'Connexion réussie',
        'participantId' => (int)$user['id'],
        'name' => $user['name'],
        'pseudo' => $user['pseudo'],
        'is_admin' => (bool)$user['is_admin']
    ]);
});

// =========================================================
// 🧩 3. Questions aléatoires (POST) - GESTION UNIQUE ET GLOBALE
// =========================================================
$app->post('/api/quiz/questions', function (Request $request, Response $response) use ($pdo) {

    $limit = 10; // Nombre de questions par quiz
    
    try {
        // 1. Sélectionner les questions non encore utilisées (is_used = 0)
        $stmt = $pdo->prepare("
            SELECT id, question, category, difficulty, correct_answer, incorrect_answers 
            FROM questions
            WHERE is_used = 0 
            ORDER BY RAND()
            LIMIT :limit
        ");
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT); 
        $stmt->execute();
        $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Si nous n'avons pas assez de questions non utilisées, réinitialiser tout et recommencer
        if (count($questions) < $limit) {
             // Marquer toutes les questions comme non utilisées (is_used = 0)
            $pdo->query("UPDATE questions SET is_used = 0");
            
            // Re-sélectionner les questions
            $stmt = $pdo->prepare("
                SELECT id, question, category, difficulty, correct_answer, incorrect_answers 
                FROM questions
                ORDER BY RAND()
                LIMIT :limit
            ");
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT); 
            $stmt->execute();
            $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        // 2. Marquer les questions sélectionnées comme utilisées (is_used = 1)
        $idsToMarkUsed = array_map(function($q) { return (int)$q['id']; }, $questions);
        
        if (!empty($idsToMarkUsed)) {
            // Création des placeholders (?, ?, ?) pour la clause IN
            $placeholders = implode(',', array_fill(0, count($idsToMarkUsed), '?'));
            
            // Mise à jour permanente dans la table 'questions'
            $sql = "UPDATE questions SET is_used = 1 WHERE id IN ($placeholders)";
            $update = $pdo->prepare($sql);
            $update->execute($idsToMarkUsed);
        }

        // 3. Formatage et Envoi des questions (Restauration de la clé 'answers')
        $formattedQuestions = array_map(function ($q) {
            $incorrect = json_decode($q['incorrect_answers'], true) ?? [];
            $allAnswers = $incorrect;
            $allAnswers[] = $q['correct_answer'];
            shuffle($allAnswers);

            return [
                'id' => $q['id'],
                'question' => $q['question'],
                'category' => $q['category'],
                'difficulty' => $q['difficulty'],
                'answers' => $allAnswers, // <-- RESTAURATION de la clé 'answers'
            ];
        }, $questions);

        return setJsonResponse($response, $formattedQuestions);

    } catch (Exception $e) {
        error_log("Erreur API /api/quiz/questions: " . $e->getMessage());
        return setJsonResponse($response, ['error' => 'Erreur serveur interne lors de la sélection des questions.'], 500);
    }
});


// ===============================
// 🧩 Soumettre réponse (Simulé, le serveur Socket gère le score final)
// ===============================
$app->post('/api/quiz/answer', function (Request $request, Response $response) use ($pdo) {
    $data = json_decode($request->getBody()->getContents(), true);
    $player_id = $data['player_id'] ?? null;
    $question_id = $data['question_id'] ?? null;
    $submitted_answer = trim($data['answer'] ?? '');

    if (!$player_id || !$question_id || $submitted_answer === '') {
        return setJsonResponse($response, ['error' => 'Données manquantes.'], 400);
    }

    $stmt = $pdo->prepare("SELECT correct_answer FROM questions WHERE id = ?");
    $stmt->execute([$question_id]);
    $correct = $stmt->fetchColumn(); 

    $isCorrect = false;
    $scoreEarned = 0;
    
    if ($correct) {
        $correct_clean = strtolower(trim($correct)); 
        $answer_clean = strtolower($submitted_answer); 
        $isCorrect = ($answer_clean === $correct_clean);
        
        // Simuler la mise à jour du score pour l'API pure, même si Socket.io est la source de vérité
        if ($isCorrect) {
            $stmt_update = $pdo->prepare("UPDATE participants SET score = score + 1 WHERE id = ?");
            $stmt_update->execute([$player_id]);
            $scoreEarned = 1;
        }
    }

    return setJsonResponse($response, [
        'correct_answer' => $correct, 
        'is_correct' => $isCorrect, 
        'score_earned' => $scoreEarned
    ]);
});

// ===============================
// 🧾 Score & Classement
// ===============================
$app->get('/api/score/{id}', function (Request $request, Response $response, array $args) use ($pdo) {
    $id = $args['id'];
    $stmt = $pdo->prepare("SELECT score FROM participants WHERE id = ?");
    $stmt->execute([$id]);
    $score = $stmt->fetchColumn();

    return setJsonResponse($response, ['score' => (int)$score]);
});

$app->get('/api/leaderboard', function (Request $request, Response $response) use ($pdo) {
    $stmt = $pdo->query("SELECT pseudo, score FROM participants ORDER BY score DESC LIMIT 10");
    $leaderboard = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return setJsonResponse($response, $leaderboard);
});

// ===============================
// 🚨 Routes de Gestion du Lobby
// ===============================

$app->post('/api/players/ready', function (Request $request, Response $response) use ($pdo) {
    $data = json_decode($request->getBody()->getContents(), true);
    $player_id = $data['player_id'] ?? null;

    if (!$player_id) {
        return setJsonResponse($response, ['error' => 'ID joueur manquant.'], 400);
    }

    $stmt = $pdo->prepare("UPDATE participants SET is_ready = 1 WHERE id = ?");
    $stmt->execute([$player_id]);

    return setJsonResponse($response, ['success' => true]);
});

$app->get('/api/players/ready-list', function (Request $request, Response $response) use ($pdo) {
    $stmt = $pdo->query("SELECT pseudo, is_admin, is_ready FROM participants ORDER BY id ASC");
    $players = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return setJsonResponse($response, $players);
});

$app->post('/api/game/start', function (Request $request, Response $response) use ($pdo) {
    $data = json_decode($request->getBody()->getContents(), true);
    $admin_id = $data['admin_id'] ?? null;

    if (!$admin_id) {
        return setJsonResponse($response, ['error' => 'ID admin manquant.'], 400);
    }

    $stmt = $pdo->prepare("SELECT is_admin FROM participants WHERE id = ?");
    $stmt->execute([$admin_id]);
    $isAdmin = $stmt->fetchColumn();

    if (!$isAdmin) {
        return setJsonResponse($response, ['error' => 'Action réservée à l’administrateur.'], 403);
    }

    $pdo->query("UPDATE participants SET game_started = 1");

    return setJsonResponse($response, ['message' => 'Partie lancée !']);
});

$app->get('/api/game/status', function (Request $request, Response $response) use ($pdo) {
    $stmt = $pdo->query("SELECT game_started FROM participants LIMIT 1");
    $status = $stmt->fetchColumn();

    return setJsonResponse($response, ['started' => (bool)$status]);
});

// ===============================
// 🧹 Route : Réinitialisation du jeu (pour l'admin)
// ===============================
$app->post('/api/game/reset', function (Request $request, Response $response) use ($pdo) {
    // Note: cette route doit être utilisée par l'administrateur
    try {
        // Réinitialiser le statut 'is_used' des questions
        $pdo->query("UPDATE questions SET is_used = 0");
        
        // Réinitialiser les scores, le statut 'ready' et 'game_started' des participants
        $pdo->query("UPDATE participants SET score = 0, is_ready = 0, game_started = 0");

        return setJsonResponse($response, ['message' => 'Le jeu a été complètement réinitialisé. Les questions peuvent être réutilisées.'], 200);

    } catch (Exception $e) {
        error_log("Erreur lors de la réinitialisation du jeu: " . $e->getMessage());
        return setJsonResponse($response, ['error' => 'Erreur serveur interne lors de la réinitialisation.'], 500);
    }
});
