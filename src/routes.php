<?php

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\App;
use App\Handlers\DatabaseHandler;
use Tuupola\Middleware\CorsMiddleware;

return function (App $app) {

    // --- Fonction utilitaire pour le formatage JSON (basée sur votre logique) ---
    // Elle remplace l'ancienne fonction setJsonResponse et utilise l'objet Response
    $setJsonResponse = function (Response $response, array $data, int $status = 200): Response {
        $response->getBody()->write(json_encode($data));
        return $response->withHeader('Content-Type', 'application/json')->withStatus($status);
    };

    // On configure le middleware CORS
    $app->add(new CorsMiddleware([
        "origin" => ["*"],
        "methods" => ["GET", "POST", "PUT", "DELETE", "OPTIONS"],
        "headers.allow" => ["Authorization", "If-Match", "If-Unmodified-Since", "Content-Type", "Accept"],
        "headers.expose" => ["Etag", "Content-Length", "X-Custom-Header"],
        "credentials" => true,
        "cache" => 86400,
    ]));

    // Route de base
    $app->get('/', function (Request $request, Response $response) use ($setJsonResponse) {
        return $setJsonResponse($response, [
            "status" => "success",
            "message" => "Welcome to the Quiz API v1",
            "db_test_url" => "/api/test-db"
        ]);
    });

    // Définition du groupe de routes pour l'API
    $app->group('/api', function (\Slim\Routing\RouteCollectorProxy $group) use ($setJsonResponse) {

        // --- MIDDLEWARE DE PARSING DE CORPS ---
        // Ajouté au niveau du groupe pour toutes les routes POST/PUT
        $group->addBodyParsingMiddleware();

        $dbHandler = new DatabaseHandler();

        // ROUTE DE TEST DE LA BASE DE DONNÉES
        $group->get('/test-db', function (Request $request, Response $response) use ($dbHandler, $setJsonResponse) {
            try {
                $pdo = $dbHandler->getConnection();
                $version = $pdo->query('SELECT version()')->fetchColumn();

                return $setJsonResponse($response, [
                    "status" => "success",
                    "message" => "Connexion PostgreSQL réussie !",
                    "db_version" => $version
                ]);
            } catch (\PDOException $e) {
                error_log("DB Error: " . $e->getMessage());
                return $setJsonResponse($response, [
                    "status" => "error",
                    "message" => "Échec de la connexion PostgreSQL : " . $e->getMessage()
                ], 500);
            }
        });


        // ===============================
        // 1. Inscription
        // ===============================
        $group->post('/register', function (Request $request, Response $response) use ($dbHandler, $setJsonResponse) {
            $data = $request->getParsedBody();
            $pdo = $dbHandler->getConnection();

            $name = trim($data['name'] ?? '');
            $pseudo = trim($data['pseudo'] ?? '');
            $email = trim($data['email'] ?? '');
            $password = trim($data['password'] ?? '');

            if (!$name || !$pseudo || !$email || !$password) {
                return $setJsonResponse($response, ['error' => 'Champs manquants.'], 400);
            }

            try {
                // Utilisation de la table 'participants' comme dans votre code
                $stmt = $pdo->prepare("SELECT id FROM participants WHERE email = ? OR pseudo = ?");
                $stmt->execute([$email, $pseudo]);
                if ($stmt->fetch()) {
                    return $setJsonResponse($response, ['error' => 'Cet email ou pseudo est déjà utilisé.'], 409);
                }

                $hash = password_hash($password, PASSWORD_DEFAULT);
                // Le rôle admin est déterminé par l'email
                $isAdmin = ($email === 'admin@quiz.com') ? 1 : 0;

                // Insertion de l'utilisateur avec score, is_ready, et game_started à 0
                $stmt = $pdo->prepare(
                    "INSERT INTO participants (name, pseudo, email, password, score, is_admin, is_ready, game_started) 
                     VALUES (?, ?, ?, ?, 0, ?, 0, 0)"
                );
                $stmt->execute([$name, $pseudo, $email, $hash, $isAdmin]);
                $id = (int) $pdo->lastInsertId();

                if ($id <= 0) {
                    error_log("ERREUR CRITIQUE: lastInsertId() a retourné un ID invalide: {$id}");
                    return $setJsonResponse($response, ['error' => 'Erreur lors de l’enregistrement de l’utilisateur. ID invalide.'], 500);
                }
                
                return $setJsonResponse($response, ['message' => 'Inscription réussie', 'id' => $id, 'is_admin' => $isAdmin], 201);
            } catch (\PDOException $e) {
                error_log("DB Error on Registration: " . $e->getMessage());
                return $setJsonResponse($response, ['error' => 'Erreur interne du serveur lors de l’inscription.'], 500);
            }
        });


        // ===============================
        // 2. Connexion
        // ===============================
        $group->post('/login', function (Request $request, Response $response) use ($dbHandler, $setJsonResponse) {
            $data = $request->getParsedBody();
            $pdo = $dbHandler->getConnection();

            $email = trim($data['email'] ?? '');
            $password = trim($data['password'] ?? '');

            if (!$email || !$password) {
                return $setJsonResponse($response, ['error' => 'Email ou mot de passe manquant.'], 400);
            }

            try {
                $stmt = $pdo->prepare("SELECT id, password, name, pseudo, is_admin FROM participants WHERE email = ?");
                $stmt->execute([$email]);
                $user = $stmt->fetch(\PDO::FETCH_ASSOC);

                if (!$user || !password_verify($password, $user['password'])) {
                    return $setJsonResponse($response, ['error' => 'Identifiants invalides.'], 401);
                }

                return $setJsonResponse($response, [
                    'message' => 'Connexion réussie',
                    'participantId' => (int)$user['id'],
                    'name' => $user['name'],
                    'pseudo' => $user['pseudo'],
                    'is_admin' => (bool)$user['is_admin']
                ]);
            } catch (\PDOException $e) {
                error_log("DB Error on Login: " . $e->getMessage());
                return $setJsonResponse($response, ['error' => 'Erreur interne du serveur lors de la connexion.'], 500);
            }
        });


        // =========================================================
        // 🧩 3. Questions aléatoires (POST) - GESTION UNIQUE ET GLOBALE
        // =========================================================
        $group->post('/quiz/questions', function (Request $request, Response $response) use ($dbHandler, $setJsonResponse) {
            $pdo = $dbHandler->getConnection();
            $limit = 10; // Nombre de questions par quiz
            
            try {
                // 1. Sélectionner les questions non encore utilisées (is_used = 0)
                $stmt = $pdo->prepare("
                    SELECT id, question, category, difficulty, correct_answer, incorrect_answers 
                    FROM questions
                    WHERE is_used = 0 
                    ORDER BY RANDOM() -- Utiliser RANDOM() pour PostgreSQL
                    LIMIT :limit
                ");
                $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT); 
                $stmt->execute();
                $questions = $stmt->fetchAll(\PDO::FETCH_ASSOC);

                // Si pas assez de questions non utilisées, réinitialiser tout et recommencer
                if (count($questions) < $limit) {
                     // Marquer toutes les questions comme non utilisées (is_used = 0)
                    $pdo->query("UPDATE questions SET is_used = 0");
                    
                    // Re-sélectionner les questions
                    $stmt = $pdo->prepare("
                        SELECT id, question, category, difficulty, correct_answer, incorrect_answers 
                        FROM questions
                        ORDER BY RANDOM()
                        LIMIT :limit
                    ");
                    $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT); 
                    $stmt->execute();
                    $questions = $stmt->fetchAll(\PDO::FETCH_ASSOC);
                }

                // 2. Marquer les questions sélectionnées comme utilisées (is_used = 1)
                $idsToMarkUsed = array_map(function($q) { return (int)$q['id']; }, $questions);
                
                if (!empty($idsToMarkUsed)) {
                    // Création des placeholders (?, ?, ?) pour la clause IN
                    $placeholders = implode(',', array_fill(0, count($idsToMarkUsed), '?'));
                    
                    // Mise à jour dans la table 'questions'
                    $sql = "UPDATE questions SET is_used = 1 WHERE id IN ($placeholders)";
                    $update = $pdo->prepare($sql);
                    $update->execute($idsToMarkUsed);
                }

                // 3. Formatage et Envoi des questions
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
                    ];
                }, $questions);

                return $setJsonResponse($response, $formattedQuestions);

            } catch (\PDOException $e) {
                error_log("Erreur API /api/quiz/questions: " . $e->getMessage());
                return $setJsonResponse($response, ['error' => 'Erreur serveur interne lors de la sélection des questions.'], 500);
            }
        });


        // ===============================
        // 🧩 Soumettre réponse 
        // ===============================
        $group->post('/quiz/answer', function (Request $request, Response $response) use ($dbHandler, $setJsonResponse) {
            $data = $request->getParsedBody();
            $pdo = $dbHandler->getConnection();

            $player_id = $data['player_id'] ?? null;
            $question_id = $data['question_id'] ?? null;
            $submitted_answer = trim($data['answer'] ?? '');

            if (!$player_id || !$question_id || $submitted_answer === '') {
                return $setJsonResponse($response, ['error' => 'Données manquantes.'], 400);
            }

            try {
                $stmt = $pdo->prepare("SELECT correct_answer FROM questions WHERE id = ?");
                $stmt->execute([$question_id]);
                $correct = $stmt->fetchColumn(); 

                $isCorrect = false;
                $scoreEarned = 0;
                
                if ($correct) {
                    $correct_clean = strtolower(trim($correct)); 
                    $answer_clean = strtolower($submitted_answer); 
                    $isCorrect = ($answer_clean === $correct_clean);
                    
                    // Mise à jour du score si la réponse est correcte
                    if ($isCorrect) {
                        $stmt_update = $pdo->prepare("UPDATE participants SET score = score + 1 WHERE id = ?");
                        $stmt_update->execute([$player_id]);
                        $scoreEarned = 1;
                    }
                }

                return $setJsonResponse($response, [
                    'correct_answer' => $correct, 
                    'is_correct' => $isCorrect, 
                    'score_earned' => $scoreEarned
                ]);
            } catch (\PDOException $e) {
                error_log("DB Error on Quiz Answer: " . $e->getMessage());
                return $setJsonResponse($response, ['error' => 'Erreur interne du serveur lors de la soumission de la réponse.'], 500);
            }
        });

        // ===============================
        // 🧾 Score & Classement
        // ===============================
        $group->get('/score/{id}', function (Request $request, Response $response, array $args) use ($dbHandler, $setJsonResponse) {
            $pdo = $dbHandler->getConnection();
            $id = $args['id'];
            
            try {
                $stmt = $pdo->prepare("SELECT score FROM participants WHERE id = ?");
                $stmt->execute([$id]);
                $score = $stmt->fetchColumn();

                return $setJsonResponse($response, ['score' => (int)$score]);
            } catch (\PDOException $e) {
                error_log("DB Error on Score Fetch: " . $e->getMessage());
                return $setJsonResponse($response, ['error' => 'Erreur interne du serveur.'], 500);
            }
        });

        $group->get('/leaderboard', function (Request $request, Response $response) use ($dbHandler, $setJsonResponse) {
            $pdo = $dbHandler->getConnection();
            
            try {
                $stmt = $pdo->query("SELECT pseudo, score FROM participants ORDER BY score DESC LIMIT 10");
                $leaderboard = $stmt->fetchAll(\PDO::FETCH_ASSOC);

                return $setJsonResponse($response, $leaderboard);
            } catch (\PDOException $e) {
                error_log("DB Error on Leaderboard: " . $e->getMessage());
                return $setJsonResponse($response, ['error' => 'Erreur interne du serveur.'], 500);
            }
        });

        // ===============================
        // 🚨 Routes de Gestion du Lobby
        // ===============================

        $group->post('/players/ready', function (Request $request, Response $response) use ($dbHandler, $setJsonResponse) {
            $data = $request->getParsedBody();
            $pdo = $dbHandler->getConnection();
            $player_id = $data['player_id'] ?? null;

            if (!$player_id) {
                return $setJsonResponse($response, ['error' => 'ID joueur manquant.'], 400);
            }

            try {
                $stmt = $pdo->prepare("UPDATE participants SET is_ready = 1 WHERE id = ?");
                $stmt->execute([$player_id]);

                return $setJsonResponse($response, ['success' => true]);
            } catch (\PDOException $e) {
                error_log("DB Error on Player Ready: " . $e->getMessage());
                return $setJsonResponse($response, ['error' => 'Erreur interne du serveur.'], 500);
            }
        });

        $group->get('/players/ready-list', function (Request $request, Response $response) use ($dbHandler, $setJsonResponse) {
            $pdo = $dbHandler->getConnection();
            
            try {
                $stmt = $pdo->query("SELECT pseudo, is_admin, is_ready FROM participants ORDER BY id ASC");
                $players = $stmt->fetchAll(\PDO::FETCH_ASSOC);

                return $setJsonResponse($response, $players);
            } catch (\PDOException $e) {
                error_log("DB Error on Ready List: " . $e->getMessage());
                return $setJsonResponse($response, ['error' => 'Erreur interne du serveur.'], 500);
            }
        });

        $group->post('/game/start', function (Request $request, Response $response) use ($dbHandler, $setJsonResponse) {
            $data = $request->getParsedBody();
            $pdo = $dbHandler->getConnection();
            $admin_id = $data['admin_id'] ?? null;

            if (!$admin_id) {
                return $setJsonResponse($response, ['error' => 'ID admin manquant.'], 400);
            }

            try {
                $stmt = $pdo->prepare("SELECT is_admin FROM participants WHERE id = ?");
                $stmt->execute([$admin_id]);
                $isAdmin = $stmt->fetchColumn();

                if (!$isAdmin) {
                    return $setJsonResponse($response, ['error' => 'Action réservée à l’administrateur.'], 403);
                }

                $pdo->query("UPDATE participants SET game_started = 1");

                return $setJsonResponse($response, ['message' => 'Partie lancée !']);
            } catch (\PDOException $e) {
                error_log("DB Error on Game Start: " . $e->getMessage());
                return $setJsonResponse($response, ['error' => 'Erreur interne du serveur.'], 500);
            }
        });

        $group->get('/game/status', function (Request $request, Response $response) use ($dbHandler, $setJsonResponse) {
            $pdo = $dbHandler->getConnection();
            
            try {
                $stmt = $pdo->query("SELECT game_started FROM participants LIMIT 1");
                $status = $stmt->fetchColumn();

                return $setJsonResponse($response, ['started' => (bool)$status]);
            } catch (\PDOException $e) {
                error_log("DB Error on Game Status: " . $e->getMessage());
                return $setJsonResponse($response, ['error' => 'Erreur interne du serveur.'], 500);
            }
        });

        // ===============================
        // 🧹 Route : Réinitialisation du jeu (pour l'admin)
        // ===============================
        $group->post('/game/reset', function (Request $request, Response $response) use ($dbHandler, $setJsonResponse) {
            $pdo = $dbHandler->getConnection();
            
            try {
                // Réinitialiser le statut 'is_used' des questions
                $pdo->query("UPDATE questions SET is_used = 0");
                
                // Réinitialiser les scores, le statut 'ready' et 'game_started' des participants
                $pdo->query("UPDATE participants SET score = 0, is_ready = 0, game_started = 0");

                return $setJsonResponse($response, ['message' => 'Le jeu a été complètement réinitialisé. Les questions peuvent être réutilisées.'], 200);

            } catch (\PDOException $e) {
                error_log("Erreur lors de la réinitialisation du jeu: " . $e->getMessage());
                return $setJsonResponse($response, ['error' => 'Erreur serveur interne lors de la réinitialisation.'], 500);
            }
        });

    });

};
