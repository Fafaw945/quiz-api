<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

// ===============================
// 🔹 Fonction utilitaire JSON
// ===============================
function setJsonResponse(Response $response, array $data, int $status = 200): Response {
    $response = $response->withHeader('Content-Type', 'application/json')->withStatus($status);
    $response->getBody()->write(json_encode($data));
    return $response;
}

// Récupérer le container Slim
$container = $app->getContainer();

// ===============================
// 1️⃣ Inscription
// ===============================
$app->post('/api/register', function (Request $request, Response $response) use ($container) {
    $pdo = $container->get('db');
    $data = json_decode($request->getBody()->getContents(), true);

    $name = trim($data['name'] ?? '');
    $pseudo = trim($data['pseudo'] ?? '');
    $email = trim($data['email'] ?? '');
    $password = trim($data['password'] ?? '');

    if (!$name || !$pseudo || !$email || !$password) {
        return setJsonResponse($response, ['error' => 'Champs manquants.'], 400);
    }

    $stmt = $pdo->prepare("SELECT id FROM participants WHERE email = :email OR pseudo = :pseudo");
    $stmt->execute(['email' => $email, 'pseudo' => $pseudo]);
    if ($stmt->fetch()) {
        return setJsonResponse($response, ['error' => 'Cet email ou pseudo est déjà utilisé.'], 409);
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);
    $isAdmin = ($email === 'admin@quiz.com') ? 1 : 0;

    $stmt = $pdo->prepare(
        "INSERT INTO participants (name, pseudo, email, password, score, is_admin, is_ready, game_started) 
         VALUES (:name, :pseudo, :email, :password, 0, :is_admin, FALSE, FALSE)"
    );
    $stmt->execute([
        'name' => $name,
        'pseudo' => $pseudo,
        'email' => $email,
        'password' => $hash,
        'is_admin' => $isAdmin
    ]);

    $id = (int)$pdo->lastInsertId();

    return setJsonResponse($response, ['message' => 'Inscription réussie', 'id' => $id, 'is_admin' => $isAdmin], 201);
});

// ===============================
// 2️⃣ Connexion
// ===============================
$app->post('/api/login', function (Request $request, Response $response) use ($container) {
    $pdo = $container->get('db');
    $data = json_decode($request->getBody()->getContents(), true);

    $email = trim($data['email'] ?? '');
    $password = trim($data['password'] ?? '');

    if (!$email || !$password) {
        return setJsonResponse($response, ['error' => 'Email ou mot de passe manquant.'], 400);
    }

    $stmt = $pdo->prepare("SELECT id, password, name, pseudo, is_admin FROM participants WHERE email = :email");
    $stmt->execute(['email' => $email]);
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

// ===============================
// 3️⃣ Questions aléatoires (POST et GET)
// ===============================
$fetchQuestions = function () use ($container) {
    $pdo = $container->get('db');
    $limit = 10;

    // CORRIGÉ : "RANDOM()" est la syntaxe PostgreSQL. "RAND()" est pour MySQL.
    $stmt = $pdo->query("SELECT id, question, category, difficulty, correct_answer, incorrect_answers 
                          FROM questions WHERE is_used = FALSE ORDER BY RANDOM() LIMIT $limit");
    $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($questions) < $limit) {
        $pdo->query("UPDATE questions SET is_used = FALSE");
        
        // CORRIGÉ : "RANDOM()" est la syntaxe PostgreSQL. "RAND()" est pour MySQL.
        $stmt = $pdo->query("SELECT id, question, category, difficulty, correct_answer, incorrect_answers 
                              FROM questions ORDER BY RANDOM() LIMIT $limit");
        $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    $idsToMarkUsed = array_map(fn($q) => (int)$q['id'], $questions);
    if (!empty($idsToMarkUsed)) {
        $idsStr = implode(',', $idsToMarkUsed);
        $pdo->query("UPDATE questions SET is_used = TRUE WHERE id IN ($idsStr)");
    }

    $formatted = array_map(function ($q) {
        $incorrect = json_decode($q['incorrect_answers'], true) ?? [];
        $allAnswers = $incorrect;
        $allAnswers[] = $q['correct_answer'];
        shuffle($allAnswers);
        return [
            'id' => $q['id'],
            'question' => $q['question'],
            'category' => $q['category'],
            'difficulty' => $q['difficulty'],
            'answers' => $allAnswers
        ];
    }, $questions);

    return $formatted;
};

// Route POST
$app->post('/api/quiz/questions', function (Request $request, Response $response) use ($fetchQuestions) {
    return setJsonResponse($response, $fetchQuestions());
});

// Route GET (Postman friendly)
$app->get('/api/questions', function (Request $request, Response $response) use ($fetchQuestions) {
    return setJsonResponse($response, $fetchQuestions());
});

// ===============================
// 4️⃣ Soumettre réponse
// ===============================
$app->post('/api/quiz/answer', function (Request $request, Response $response) use ($container) {
    $pdo = $container->get('db');
    $data = json_decode($request->getBody()->getContents(), true);

    $player_id = $data['player_id'] ?? null;
    $question_id = $data['question_id'] ?? null;
    $submitted_answer = trim($data['answer'] ?? '');

    if (!$player_id || !$question_id || $submitted_answer === '') {
        return setJsonResponse($response, ['error' => 'Données manquantes.'], 400);
    }

    $stmt = $pdo->prepare("SELECT correct_answer FROM questions WHERE id = :id");
    $stmt->execute(['id' => $question_id]);
    $correct = $stmt->fetchColumn();

    $isCorrect = false;
    $scoreEarned = 0;

    if ($correct) {
        $isCorrect = (strtolower(trim($submitted_answer)) === strtolower(trim($correct)));
        if ($isCorrect) {
            $stmt_update = $pdo->prepare("UPDATE participants SET score = score + 1 WHERE id = :id");
            $stmt_update->execute(['id' => $player_id]);
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
// 5️⃣ Score & Classement
// ===============================
$app->get('/api/score/{id}', function (Request $request, Response $response, array $args) use ($container) {
    $pdo = $container->get('db');
    $id = $args['id'];
    $stmt = $pdo->prepare("SELECT score FROM participants WHERE id = :id");
    $stmt->execute(['id' => $id]);
    $score = $stmt->fetchColumn();

    return setJsonResponse($response, ['score' => (int)$score]);
});

$app->get('/api/leaderboard', function (Request $request, Response $response) use ($container) {
    $pdo = $container->get('db');
    $stmt = $pdo->query("SELECT pseudo, score FROM participants ORDER BY score DESC LIMIT 10");
    $leaderboard = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return setJsonResponse($response, $leaderboard);
});

// ===============================
// 6️⃣ Gestion du Lobby
// ===============================
$app->post('/api/players/ready', function (Request $request, Response $response) use ($container) {
    $pdo = $container->get('db');
    $data = json_decode($request->getBody()->getContents(), true);
    $player_id = $data['player_id'] ?? null;

    if (!$player_id) return setJsonResponse($response, ['error' => 'ID joueur manquant.'], 400);

    $stmt = $pdo->prepare("UPDATE participants SET is_ready = TRUE WHERE id = :id");
    $stmt->execute(['id' => $player_id]);

    return setJsonResponse($response, ['success' => true]);
});

$app->get('/api/players/ready-list', function (Request $request, Response $response) use ($container) {
    $pdo = $container->get('db');
    $stmt = $pdo->query("SELECT pseudo, is_admin, is_ready FROM participants ORDER BY id ASC");
    $players = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return setJsonResponse($response, $players);
});

// ===============================
// 7️⃣ Gestion du jeu
// ===============================
$app->post('/api/game/start', function (Request $request, Response $response) use ($container) {
    $pdo = $container->get('db');
    $data = json_decode($request->getBody()->getContents(), true);
    $admin_id = $data['admin_id'] ?? null;

    if (!$admin_id) return setJsonResponse($response, ['error' => 'ID admin manquant.'], 400);

    $stmt = $pdo->prepare("SELECT is_admin FROM participants WHERE id = :id");
    $stmt->execute(['id' => $admin_id]);
    $isAdmin = $stmt->fetchColumn();

    if (!$isAdmin) return setJsonResponse($response, ['error' => 'Action réservée à l’administrateur.'], 403);

    $pdo->query("UPDATE participants SET game_started = TRUE");

    return setJsonResponse($response, ['message' => 'Partie lancée !']);
});

$app->get('/api/game/status', function (Request $request, Response $response) use ($container) {
    $pdo = $container->get('db');
    $stmt = $pdo->query("SELECT game_started FROM participants LIMIT 1");
    $status = $stmt->fetchColumn();

    return setJsonResponse($response, ['started' => (bool)$status]);
});

$app->post('/api/game/reset', function (Request $request, Response $response) use ($container) {
    $pdo = $container->get('db');
    try {
        $pdo->query("UPDATE questions SET is_used = FALSE");
        $pdo->query("UPDATE participants SET score = 0, is_ready = FALSE, game_started = FALSE");

        return setJsonResponse($response, ['message' => 'Le jeu a été complètement réinitialisé.'], 200);
    } catch (Exception $e) {
        error_log("Erreur lors de la réinitialisation : " . $e->getMessage());
        return setJsonResponse($response, ['error' => 'Erreur serveur interne.'], 500);
    }
});