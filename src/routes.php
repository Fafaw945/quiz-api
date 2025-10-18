<?php
// Note: Ce fichier suppose que $app (instance de Slim) et $pdo (connexion PDO)
// sont disponibles et passés via le mécanisme 'use' ou définis globalement.

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

// ===============================
// ✅ Inscription (CORRIGÉ : Vérification de l'ID)
// ===============================
$app->post('/api/register', function (Request $request, Response $response) use ($pdo) {
    $data = json_decode($request->getBody()->getContents(), true);
    $name = trim($data['name'] ?? '');
    $pseudo = trim($data['pseudo'] ?? '');
    $email = trim($data['email'] ?? '');
    $password = trim($data['password'] ?? '');

    if (!$name || !$pseudo || !$email || !$password) {
        return $response->withHeader('Content-Type', 'application/json')->withStatus(400)
            ->write(json_encode(['error' => 'Champs manquants.']));
    }

    // Vérifie doublons
    $stmt = $pdo->prepare("SELECT id FROM participants WHERE email = ? OR pseudo = ?");
    $stmt->execute([$email, $pseudo]);
    if ($stmt->fetch()) {
        return $response->withHeader('Content-Type', 'application/json')->withStatus(409)
            ->write(json_encode(['error' => 'Cet email ou pseudo est déjà utilisé.']));
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);
    $isAdmin = ($email === 'admin@quiz.com') ? 1 : 0;

    $stmt = $pdo->prepare(
        "INSERT INTO participants (name, pseudo, email, password, score, is_admin, is_ready, game_started) 
         VALUES (?, ?, ?, ?, 0, ?, 0, 0)"
    );
    $stmt->execute([$name, $pseudo, $email, $hash, $isAdmin]);
    $id = $pdo->lastInsertId();

    // 🔑 CONVERSION EN ENTIER : S'assure que l'ID est un entier non nul pour l'envoi au client.
    $id = (int)$id; 

    if ($id <= 0) {
        error_log("ERREUR CRITIQUE: lastInsertId() a retourné un ID invalide: {$id}");
        return $response->withHeader('Content-Type', 'application/json')->withStatus(500)
            ->write(json_encode(['error' => 'Erreur lors de l\'enregistrement de l\'utilisateur. ID invalide.']));
    }
    
    error_log("Inscription réussie. ID BDD du joueur: {$id}");

    $response->getBody()->write(json_encode(['message' => 'Inscription réussie', 'id' => $id, 'is_admin' => $isAdmin]));
    return $response->withHeader('Content-Type', 'application/json')->withStatus(201);
});

// ===============================
// ✅ Connexion (Inchangé)
// ===============================
$app->post('/api/login', function (Request $request, Response $response) use ($pdo) {
    $data = json_decode($request->getBody()->getContents(), true);
    $email = trim($data['email'] ?? '');
    $password = trim($data['password'] ?? '');

    if (!$email || !$password) {
        return $response->withHeader('Content-Type', 'application/json')->withStatus(400)
            ->write(json_encode(['error' => 'Email ou mot de passe manquant.']));
    }

    $stmt = $pdo->prepare("SELECT id, password, name, pseudo, is_admin FROM participants WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user || !password_verify($password, $user['password'])) {
        return $response->withHeader('Content-Type', 'application/json')->withStatus(401)
            ->write(json_encode(['error' => 'Identifiants invalides.']));
    }

    $response->getBody()->write(json_encode([
        'message' => 'Connexion réussie',
        'participantId' => (int)$user['id'], // Assure que l'ID est un entier
        'name' => $user['name'],
        'pseudo' => $user['pseudo'],
        'is_admin' => (bool)$user['is_admin']
    ]));
    return $response->withHeader('Content-Type', 'application/json');
});

// ===============================
// 🧩 Questions aléatoires (Inchangé)
// ===============================
$getQuestionsHandler = function (Request $request, Response $response) use ($pdo) {
    $limit = 10;
    $stmt = $pdo->query("SELECT * FROM questions ORDER BY RAND() LIMIT {$limit}");
    $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
            'answers' => $allAnswers,
            'correct_answer' => $q['correct_answer']
        ];
    }, $questions);

    $response->getBody()->write(json_encode($formattedQuestions));
    return $response->withHeader('Content-Type', 'application/json');
};

$app->get('/api/quiz/questions', $getQuestionsHandler);


// ===============================
// 🗑️ Route : Suppression des questions jouées (Neutralisée)
// ===============================
$app->post('/api/questions/delete', function (Request $request, Response $response) use ($pdo) {
    $data = json_decode($request->getBody()->getContents(), true);
    $question_ids = $data['ids'] ?? [];
    $count = is_array($question_ids) ? count($question_ids) : 0;
    
    error_log("ATTENTION: Route /api/questions/delete appelée mais neutralisée. Tentative de suppression de {$count} IDs.");

    $response->getBody()->write(json_encode(['success' => true, 'deleted_count' => 0, 'message' => 'Fonction de suppression temporairement désactivée.']));
    return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
});

// ===============================
// 🧩 Soumettre réponse (CORRIGÉ : Logs finaux)
// ===============================
$app->post('/api/quiz/answer', function (Request $request, Response $response) use ($pdo) {
    $data = json_decode($request->getBody()->getContents(), true);
    $player_id = $data['player_id'] ?? null;
    $question_id = $data['question_id'] ?? null;
    $submitted_answer = trim($data['answer'] ?? '');

    // 📢 LOG CRITIQUE 1: Voir l'ID reçu avant la requête BDD
    error_log("API /answer reçu. Player ID: {$player_id}, Question ID: {$question_id}, Soumise: '{$submitted_answer}'");

    if (!$player_id || !$question_id || $submitted_answer === '') {
        return $response->withHeader('Content-Type', 'application/json')->withStatus(400)
            ->write(json_encode(['error' => 'Données manquantes.']));
    }

    // 1. Récupérer la bonne réponse
    $stmt = $pdo->prepare("SELECT correct_answer FROM questions WHERE id = ?");
    $stmt->execute([$question_id]);
    $correct = $stmt->fetchColumn(); 

    $isCorrect = false;
    $scoreEarned = 0;
    
    if ($correct) {
        $correct_clean = strtolower(trim($correct)); 
        $answer_clean = strtolower($submitted_answer); 
        
        $isCorrect = ($answer_clean === $correct_clean);
    }

    if ($isCorrect) {
        // 2. Incrémente le score dans la base de données
        $stmt_update = $pdo->prepare("UPDATE participants SET score = score + 1 WHERE id = ?");
        $success = $stmt_update->execute([$player_id]);

        // 📢 LOG CRITIQUE 2: Confirmer la mise à jour du score
        $rows_affected = $stmt_update->rowCount();
        error_log("Score UPDATE RÉSULTAT FINAL. Player ID: {$player_id}. Lignes affectées: {$rows_affected}"); 

        $scoreEarned = 1;
    } else {
        error_log("Réponse INCORRECTE. Soumise: '{$submitted_answer}', Correcte BDD: '{$correct}'");
    }

    $response->getBody()->write(json_encode([
        'correct_answer' => $correct, 
        'is_correct' => $isCorrect, 
        'score_earned' => $scoreEarned
    ])); 
    return $response->withHeader('Content-Type', 'application/json');
});

// ===============================
// 🧾 Score & Classement (Inchangé)
// ===============================
$app->get('/api/score/{id}', function (Request $request, Response $response, array $args) use ($pdo) {
    $id = $args['id'];
    $stmt = $pdo->prepare("SELECT score FROM participants WHERE id = ?");
    $stmt->execute([$id]);
    $score = $stmt->fetchColumn();

    $response->getBody()->write(json_encode(['score' => (int)$score]));
    return $response->withHeader('Content-Type', 'application/json');
});

$app->get('/api/leaderboard', function (Request $request, Response $response) use ($pdo) {
    $stmt = $pdo->query("SELECT pseudo, score FROM participants ORDER BY score DESC LIMIT 10");
    $leaderboard = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $response->getBody()->write(json_encode($leaderboard));
    return $response->withHeader('Content-Type', 'application/json');
});

// ===============================
// 🚨 Autres routes (Inchangé)
// ===============================

$app->post('/api/players/ready', function (Request $request, Response $response) use ($pdo) {
    $data = json_decode($request->getBody()->getContents(), true);
    $player_id = $data['player_id'] ?? null;

    if (!$player_id) {
        return $response->withHeader('Content-Type', 'application/json')->withStatus(400)
            ->write(json_encode(['error' => 'ID joueur manquant.']));
    }

    $stmt = $pdo->prepare("UPDATE participants SET is_ready = 1 WHERE id = ?");
    $stmt->execute([$player_id]);

    return $response->withHeader('Content-Type', 'application/json')->write(json_encode(['success' => true]));
});

$app->get('/api/players/ready-list', function (Request $request, Response $response) use ($pdo) {
    $stmt = $pdo->query("SELECT pseudo, is_admin, is_ready FROM participants ORDER BY id ASC");
    $players = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $response->getBody()->write(json_encode($players));
    return $response->withHeader('Content-Type', 'application/json');
});

$app->post('/api/game/start', function (Request $request, Response $response) use ($pdo) {
    $data = json_decode($request->getBody()->getContents(), true);
    $admin_id = $data['admin_id'] ?? null;

    if (!$admin_id) {
        return $response->withHeader('Content-Type', 'application/json')->withStatus(400)
            ->write(json_encode(['error' => 'ID admin manquant.']));
    }

    $stmt = $pdo->prepare("SELECT is_admin FROM participants WHERE id = ?");
    $stmt->execute([$admin_id]);
    $isAdmin = $stmt->fetchColumn();

    if (!$isAdmin) {
        return $response->withHeader('Content-Type', 'application/json')->withStatus(403)
            ->write(json_encode(['error' => 'Action réservée à l’administrateur.']));
    }

    $pdo->query("UPDATE participants SET game_started = 1");

    return $response->withHeader('Content-Type', 'application/json')->write(json_encode(['message' => 'Partie lancée !']));
});

$app->get('/api/game/status', function (Request $request, Response $response) use ($pdo) {
    $stmt = $pdo->query("SELECT game_started FROM participants LIMIT 1");
    $status = $stmt->fetchColumn();

    $response->getBody()->write(json_encode(['started' => (bool)$status]));
    return $response->withHeader('Content-Type', 'application/json');
});