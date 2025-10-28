<?php

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\App;
use App\Handlers\DatabaseHandler;
use App\Repositories\QuizRepository;
use App\Middleware\AuthMiddleware;
use Tuupola\Middleware\CorsMiddleware;

/**
 * Fonction utilitaire pour le formatage JSON. Définie globalement 
 * dans index.php et accessible ici.
 *
 * @param Response $response L'objet de réponse Slim.
 * @param array $data Les données à encoder en JSON.
 * @param int $status Le statut HTTP.
 * @return Response
 */
if (!function_exists('sendJsonResponse')) {
    // Si index.php ne l'a pas définie (pour les tests), on la définit ici
    function sendJsonResponse(Response $response, array $data, int $status = 200): Response {
        $response->getBody()->write(json_encode($data));
        return $response->withHeader('Content-Type', 'application/json')->withStatus($status);
    }
}


return function (App $app) {

    // Récupération de la connexion PDO globale injectée depuis index.php
    global $pdo;

    // --- Fonction utilitaire pour le formatage JSON (version locale utilisant l'alias global) ---
    $setJsonResponse = 'sendJsonResponse'; // Utilise la fonction globale

    // =============================== 
    // 🌍 2. Middleware CORS (Tuupola)
    // =============================== 
    // Gère les headers CORS pour toutes les requêtes (y compris les en-têtes d'auth)
    $app->add(new CorsMiddleware([
        "origin" => ["*"],
        "methods" => ["GET", "POST", "PUT", "DELETE", "OPTIONS"],
        "headers.allow" => ["Authorization", "Content-Type", "Accept", "X-Participant-ID"], 
        "headers.expose" => ["Etag", "Content-Length"],
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
    $app->group('/api', function (\Slim\Routing\RouteCollectorProxy $group) use ($setJsonResponse, $pdo) {

        // Ajout du Body Parsing Middleware pour toutes les routes du groupe API
        $group->addBodyParsingMiddleware();

        // --- Injection de la connexion PDO établie dans index.php ---
        $dbHandler = new DatabaseHandler($pdo); 
        $quizRepository = new QuizRepository($dbHandler);
        // --- Le middleware reçoit maintenant le Repository ET la fonction utilitaire JSON ---
        $authMiddleware = new AuthMiddleware($quizRepository, $setJsonResponse); 

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
        $group->post('/register', function (Request $request, Response $response) use ($quizRepository, $setJsonResponse) {
            $data = $request->getParsedBody();

            $name = trim($data['name'] ?? '');
            $pseudo = trim($data['pseudo'] ?? '');
            $email = trim($data['email'] ?? '');
            $password = trim($data['password'] ?? '');

            if (!$name || !$pseudo || !$email || !$password) {
                return $setJsonResponse($response, ['error' => 'Champs manquants.'], 400);
            }

            try {
                if ($quizRepository->participantExists($email, $pseudo)) {
                    return $setJsonResponse($response, ['error' => 'Cet email ou pseudo est déjà utilisé.'], 409);
                }

                $hash = password_hash($password, PASSWORD_DEFAULT);
                // L'admin est défini dans la route par son email
                $isAdmin = ($email === 'admin@quiz.com') ? true : false; 

                $id = $quizRepository->createParticipant($name, $pseudo, $email, $hash, $isAdmin);

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
        $group->post('/login', function (Request $request, Response $response) use ($quizRepository, $setJsonResponse) {
            $data = $request->getParsedBody();

            $email = trim($data['email'] ?? '');
            $password = trim($data['password'] ?? '');

            if (!$email || !$password) {
                return $setJsonResponse($response, ['error' => 'Email ou mot de passe manquant.'], 400);
            }

            try {
                $user = $quizRepository->findParticipantByEmail($email);

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
        // 🔒 Routes sécurisées par AuthMiddleware
        // =========================================================
        $group->group('', function (\Slim\Routing\RouteCollectorProxy $secureGroup) use ($quizRepository, $setJsonResponse) {

            // =========================================================
            // 🧩 3. Questions aléatoires (POST)
            // =========================================================
            $secureGroup->post('/quiz/questions', function (Request $request, Response $response) use ($quizRepository, $setJsonResponse) {
                try {
                    // La logique de sélection, réinitialisation, et marquage est maintenant dans le Repository
                    $questions = $quizRepository->getQuizQuestions(10); 

                    // 3. Formatage et Envoi des questions
                    $formattedQuestions = array_map(function ($q) {
                        // Décode les mauvaises réponses du format JSONB
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
                            // NOTE: La réponse correcte n'est PAS envoyée ici.
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
            $secureGroup->post('/quiz/answer', function (Request $request, Response $response) use ($quizRepository, $setJsonResponse) {
                $data = $request->getParsedBody();
                $player_id = $request->getAttribute('participant_id'); // Récupéré du middleware
                
                $question_id = $data['question_id'] ?? null;
                $submitted_answer = trim($data['answer'] ?? '');

                if (!$player_id || !$question_id || $submitted_answer === '') {
                    // Normalement l'ID joueur est toujours présent grâce au middleware, mais on garde la sécurité
                    return $setJsonResponse($response, ['error' => 'Données manquantes ou ID joueur invalide.'], 400);
                }

                try {
                    $correct = $quizRepository->getCorrectAnswer((int)$question_id); 

                    $isCorrect = false;
                    $scoreEarned = 0;
                    
                    if ($correct) {
                        // Comparaison sans sensibilité à la casse et sans espaces blancs inutiles
                        $correct_clean = strtolower(trim($correct)); 
                        $answer_clean = strtolower($submitted_answer); 
                        $isCorrect = ($answer_clean === $correct_clean);
                        
                        if ($isCorrect) {
                            $quizRepository->incrementParticipantScore($player_id);
                            $scoreEarned = 1;
                        }
                    } else {
                         // Question non trouvée
                        return $setJsonResponse($response, ['error' => 'Question non trouvée.'], 404);
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
            
            // Récupérer le score du joueur authentifié
            $secureGroup->get('/score', function (Request $request, Response $response) use ($quizRepository, $setJsonResponse) {
                $id = $request->getAttribute('participant_id'); // ID récupéré du middleware
                
                try {
                    $score = $quizRepository->getParticipantScore((int)$id);

                    if ($score === false) {
                        // Ce cas ne devrait pas arriver si le middleware fonctionne bien
                        return $setJsonResponse($response, ['error' => 'Participant non trouvé.'], 404);
                    }

                    return $setJsonResponse($response, ['score' => $score]);
                } catch (\PDOException $e) {
                    error_log("DB Error on Score Fetch: " . $e->getMessage());
                    return $setJsonResponse($response, ['error' => 'Erreur interne du serveur.'], 500);
                }
            });


            $secureGroup->get('/leaderboard', function (Request $request, Response $response) use ($quizRepository, $setJsonResponse) {
                try {
                    $leaderboard = $quizRepository->getLeaderboard();
                    return $setJsonResponse($response, $leaderboard);
                } catch (\PDOException $e) {
                    error_log("DB Error on Leaderboard: " . $e->getMessage());
                    return $setJsonResponse($response, ['error' => 'Erreur interne du serveur.'], 500);
                }
            });

            // ===============================
            // 🚨 Routes de Gestion du Lobby
            // ===============================

            $secureGroup->post('/players/ready', function (Request $request, Response $response) use ($quizRepository, $setJsonResponse) {
                // ID joueur récupéré du middleware
                $player_id = $request->getAttribute('participant_id');

                try {
                    $quizRepository->setParticipantReady($player_id);
                    return $setJsonResponse($response, ['success' => true, 'player_id' => $player_id, 'message' => 'Statut prêt mis à jour.']);
                } catch (\PDOException $e) {
                    error_log("DB Error on Player Ready: " . $e->getMessage());
                    return $setJsonResponse($response, ['error' => 'Erreur interne du serveur.'], 500);
                }
            });

            $secureGroup->get('/players/ready-list', function (Request $request, Response $response) use ($quizRepository, $setJsonResponse) {
                try {
                    $players = $quizRepository->getReadyParticipantsList();
                    return $setJsonResponse($response, $players);
                } catch (\PDOException $e) {
                    error_log("DB Error on Ready List: " . $e->getMessage());
                    return $setJsonResponse($response, ['error' => 'Erreur interne du serveur.'], 500);
                }
            });

            $secureGroup->post('/game/start', function (Request $request, Response $response) use ($quizRepository, $setJsonResponse) {
                // ID joueur récupéré du middleware
                $admin_id = $request->getAttribute('participant_id'); 

                try {
                    if (!$quizRepository->isAdmin($admin_id)) {
                        return $setJsonResponse($response, ['error' => 'Action réservée à l’administrateur.'], 403);
                    }

                    $quizRepository->startGame();
                    return $setJsonResponse($response, ['message' => 'Partie lancée !']);
                } catch (\PDOException $e) {
                    error_log("DB Error on Game Start: " . $e->getMessage());
                    return $setJsonResponse($response, ['error' => 'Erreur interne du serveur.'], 500);
                }
            });

            $secureGroup->get('/game/status', function (Request $request, Response $response) use ($quizRepository, $setJsonResponse) {
                try {
                    $status = $quizRepository->getGameStatus();
                    return $setJsonResponse($response, ['started' => $status]);
                } catch (\PDOException $e) {
                    error_log("DB Error on Game Status: " . $e->getMessage());
                    return $setJsonResponse($response, ['error' => 'Erreur interne du serveur.'], 500);
                }
            });

            // ===============================
            // 🧹 Route : Réinitialisation du jeu (pour l'admin)
            // ===============================
            $secureGroup->post('/game/reset', function (Request $request, Response $response) use ($quizRepository, $setJsonResponse) {
                $admin_id = $request->getAttribute('participant_id'); 
                
                try {
                    if (!$quizRepository->isAdmin($admin_id)) {
                        return $setJsonResponse($response, ['error' => 'Action réservée à l’administrateur.'], 403);
                    }
                    
                    $quizRepository->resetGame();
                    return $setJsonResponse($response, ['message' => 'Le jeu a été complètement réinitialisé. Les questions peuvent être réutilisées.'], 200);

                } catch (\PDOException $e) {
                    error_log("Erreur lors de la réinitialisation du jeu: " . $e->getMessage());
                    return $setJsonResponse($response, ['error' => 'Erreur serveur interne lors de la réinitialisation.'], 500);
                }
            });

        // Applique le middleware d'authentification au groupe de routes sécurisées
        })->add($authMiddleware); 
    })
    // Ajout d'un traitement d'erreur pour les routes non trouvées dans le groupe /api
    ->add(function (Request $request, Response $response, \Slim\Exception\HttpNotFoundException $exception) use ($setJsonResponse) {
        if (str_starts_with($request->getUri()->getPath(), '/api')) {
             return $setJsonResponse($response, ['error' => 'Route API non trouvée.'], 404);
        }
        throw $exception;
    });
};
