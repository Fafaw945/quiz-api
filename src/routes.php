<?php

use Slim\App;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

// Le fichier de routes doit OBLIGATOIREMENT retourner une fonction pour être inclus dans Slim 4.
return function (App $app) {
    // Récupération de l'instance PDO (PostgreSQL) configurée dans index.php
    $db = $app->getContainer()->get('db');

    // --- Fonction utilitaire pour envoyer des réponses JSON ---
    // Elle remplace la fonction 'setJsonResponse' dans le corps que vous avez fourni.
    $sendJsonResponse = function (Response $response, array $data, int $status = 200): Response {
        $response = $response->withHeader('Content-Type', 'application/json')->withStatus($status);
        $response->getBody()->write(json_encode($data));
        return $response;
    };

    // ----------------------------------------------------------------------
    // --- NOUVELLE FONCTION UTILITAIRE : Vérification des droits Admin ---
    // ----------------------------------------------------------------------
    $checkAdminAccess = function (int $adminId, \PDO $db, Response $response, callable $sendJsonResponse): ?Response {
        if (!$adminId) {
            return $sendJsonResponse($response, ['error' => 'ID admin manquant.'], 400);
        }

        $stmt = $db->prepare("SELECT is_admin FROM participants WHERE id = ?");
        $stmt->execute([$adminId]);
        // La valeur 't'/'f' de PostgreSQL est correctement convertie en booléen.
        $isAdmin = (bool)$stmt->fetchColumn();

        if (!$isAdmin) {
            return $sendJsonResponse($response, ['error' => 'Action réservée à l’administrateur.'], 403);
        }
        return null; // Accès autorisé
    };

    // ===============================
    // 1. Inscription
    // ===============================
    $app->post('/api/register', function (Request $request, Response $response) use ($db, $sendJsonResponse) {
        /*
         * NOTE DE SÉCURITÉ: Après l'inscription, un JWT ou un token de session devrait être retourné
         * pour sécuriser les requêtes suivantes. L'ID retourné ici ne doit pas être utilisé directement.
         */
        $data = $request->getParsedBody();
        $name = trim($data['name'] ?? '');
        $pseudo = trim($data['pseudo'] ?? '');
        $email = trim($data['email'] ?? '');
        $password = trim($data['password'] ?? '');

        if (!$name || !$pseudo || !$email || !$password) {
            return $sendJsonResponse($response, ['error' => 'Champs manquants.'], 400);
        }

        try {
            // Vérification anti-doublon
            $stmt = $db->prepare("SELECT id FROM participants WHERE email = ? OR pseudo = ?");
            $stmt->execute([$email, $pseudo]);
            if ($stmt->fetch()) {
                return $sendJsonResponse($response, ['error' => 'Cet email ou pseudo est déjà utilisé.'], 409);
            }

            $hash = password_hash($password, PASSWORD_DEFAULT);
            $isAdmin = ($email === 'admin@quiz.com') ? 't' : 'f'; // 't'/'f' pour PostgreSQL

            // Insertion et récupération de l'ID généré
            $stmt = $db->prepare(
                "INSERT INTO participants (name, pseudo, email, password, score, is_admin, is_ready, game_started) 
                 VALUES (?, ?, ?, ?, 0, ?, FALSE, FALSE) 
                 RETURNING id" 
            );
            $stmt->execute([$name, $pseudo, $email, $hash, $isAdmin]);
            
            $id = (int) $stmt->fetchColumn(); 

            if ($id <= 0) {
                error_log("ERREUR CRITIQUE: RETURNING id n'a pas retourné un ID valide.");
                return $sendJsonResponse($response, ['error' => 'Erreur lors de l’enregistrement de l’utilisateur. ID invalide.'], 500);
            }
            
            return $sendJsonResponse($response, ['message' => 'Inscription réussie', 'id' => $id, 'is_admin' => (bool)$isAdmin], 201);

        } catch (\PDOException $e) {
            error_log("Erreur PDO lors de l'inscription: " . $e->getMessage());
            return $sendJsonResponse($response, ['error' => 'Erreur serveur interne lors de l’inscription.'], 500);
        }
    });

    // ===============================
    // 2. Connexion
    // ===============================
    $app->post('/api/login', function (Request $request, Response $response) use ($db, $sendJsonResponse) {
        /*
         * NOTE DE SÉCURITÉ: En production, cette route devrait générer et retourner un JWT sécurisé.
         */
        $data = $request->getParsedBody();
        $email = trim($data['email'] ?? '');
        $password = trim($data['password'] ?? '');

        if (!$email || !$password) {
            return $sendJsonResponse($response, ['error' => 'Email ou mot de passe manquant.'], 400);
        }

        try {
            $stmt = $db->prepare("SELECT id, password, name, pseudo, is_admin FROM participants WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$user || !password_verify($password, $user['password'])) {
                return $sendJsonResponse($response, ['error' => 'Identifiants invalides.'], 401);
            }

            return $sendJsonResponse($response, [
                'message' => 'Connexion réussie',
                'participantId' => (int)$user['id'],
                'name' => $user['name'],
                'pseudo' => $user['pseudo'],
                'is_admin' => (bool)$user['is_admin']
            ]);
        } catch (\PDOException $e) {
            error_log("Erreur PDO lors de la connexion: " . $e->getMessage());
            return $sendJsonResponse($response, ['error' => 'Erreur serveur interne lors de la connexion.'], 500);
        }
    });

    // =========================================================
    // 🧩 3. Questions aléatoires (POST)
    // =========================================================
    $app->post('/api/quiz/questions', function (Request $request, Response $response) use ($db, $sendJsonResponse) {
        $limit = 10; // Nombre de questions par quiz
        
        try {
            // Sélectionner les questions non encore utilisées (is_used = FALSE)
            $sql_select = "
                SELECT id, question, category, difficulty, correct_answer, incorrect_answers 
                FROM questions
                WHERE is_used = FALSE 
                ORDER BY RANDOM() 
                LIMIT :limit
            ";
            $stmt = $db->prepare($sql_select);
            $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT); 
            $stmt->execute();
            $questions = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // Si le nombre de questions non utilisées est insuffisant, réinitialiser tout
            if (count($questions) < $limit) {
                 // Marquer toutes les questions comme non utilisées (is_used = FALSE)
                 $db->query("UPDATE questions SET is_used = FALSE");
                 
                 // Re-sélectionner les questions
                 $stmt->execute();
                 $questions = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            }

            // Marquer les questions sélectionnées comme utilisées (is_used = TRUE)
            $idsToMarkUsed = array_map(fn($q) => (int)$q['id'], $questions);
            
            if (!empty($idsToMarkUsed)) {
                $placeholders = implode(',', array_fill(0, count($idsToMarkUsed), '?'));
                $sql_update = "UPDATE questions SET is_used = TRUE WHERE id IN ($placeholders)";
                $update = $db->prepare($sql_update);
                $update->execute($idsToMarkUsed);
            }

            // Formatage et Envoi des questions (Mélange des réponses)
            $formattedQuestions = array_map(function ($q) {
                // Décodage de la colonne JSON 'incorrect_answers'
                $incorrect = json_decode($q['incorrect_answers'], true) ?? [];
                $allAnswers = $incorrect;
                $allAnswers[] = $q['correct_answer'];
                shuffle($allAnswers);

                // IMPORTANT : Retirer les bonnes réponses du JSON de sortie pour les joueurs.
                return [
                    'id' => $q['id'],
                    'question' => $q['question'],
                    'category' => $q['category'],
                    'difficulty' => $q['difficulty'],
                    'answers' => $allAnswers, // Liste mélangée
                ];
            }, $questions);

            return $sendJsonResponse($response, $formattedQuestions);

        } catch (\Exception $e) {
            error_log("Erreur API /api/quiz/questions: " . $e->getMessage());
            return $sendJsonResponse($response, ['error' => 'Erreur serveur interne lors de la sélection des questions.'], 500);
        }
    });


    // ===============================
    // 🧩 Soumettre réponse 
    // ===============================
    $app->post('/api/quiz/answer', function (Request $request, Response $response) use ($db, $sendJsonResponse) {
        /*
         * NOTE DE SÉCURITÉ: $data['player_id'] devrait être extrait d'un JWT validé et non du corps de la requête.
         */
        $data = $request->getParsedBody();
        $player_id = $data['player_id'] ?? null;
        $question_id = $data['question_id'] ?? null;
        $submitted_answer = trim($data['answer'] ?? '');

        if (!$player_id || !$question_id || $submitted_answer === '') {
            return $sendJsonResponse($response, ['error' => 'Données manquantes (player_id, question_id ou answer).'], 400);
        }

        try {
            // 1. Récupérer la bonne réponse
            $stmt = $db->prepare("SELECT correct_answer, difficulty FROM questions WHERE id = ?");
            $stmt->execute([$question_id]);
            $questionData = $stmt->fetch(\PDO::FETCH_ASSOC); 
            
            if (!$questionData) {
                 return $sendJsonResponse($response, ['error' => 'Question non trouvée.'], 404);
            }
            
            $correct = $questionData['correct_answer'];
            $difficulty = $questionData['difficulty'] ?? 'easy';

            $isCorrect = false;
            $scoreEarned = 0;
            $pointsMap = ['easy' => 1, 'medium' => 2, 'hard' => 3];
            $points = $pointsMap[$difficulty] ?? 1;
            
            // Comparaison des réponses (case-insensitive et trim pour robustesse)
            $correct_clean = strtolower(trim($correct)); 
            $answer_clean = strtolower($submitted_answer); 
            $isCorrect = ($answer_clean === $correct_clean);
            
            // 2. Mettre à jour le score dans la BDD
            if ($isCorrect) {
                $stmt_update = $db->prepare("UPDATE participants SET score = score + ? WHERE id = ?");
                $stmt_update->execute([$points, $player_id]);
                $scoreEarned = $points;
            }

            return $sendJsonResponse($response, [
                'correct_answer' => $correct, 
                'is_correct' => $isCorrect, 
                'score_earned' => $scoreEarned
            ]);
        } catch (\PDOException $e) {
             error_log("Erreur PDO lors de la soumission de la réponse: " . $e->getMessage());
            return $sendJsonResponse($response, ['error' => 'Erreur serveur interne.'], 500);
        }
    });

    // ===============================
    // 🧾 Score & Classement
    // ===============================
    $app->get('/api/score/{id}', function (Request $request, Response $response, array $args) use ($db, $sendJsonResponse) {
        $id = $args['id'];
        $stmt = $db->prepare("SELECT score FROM participants WHERE id = ?");
        $stmt->execute([$id]);
        $score = $stmt->fetchColumn();

        if ($score === false) {
             return $sendJsonResponse($response, ['error' => 'Participant non trouvé.'], 404);
        }

        return $sendJsonResponse($response, ['score' => (int)$score]);
    });

    $app->get('/api/leaderboard', function (Request $request, Response $response) use ($db, $sendJsonResponse) {
        $stmt = $db->query("SELECT pseudo, score FROM participants ORDER BY score DESC LIMIT 10");
        $leaderboard = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return $sendJsonResponse($response, $leaderboard);
    });

    // ===============================
    // 🚨 Routes de Gestion du Lobby
    // ===============================

    $app->post('/api/players/ready', function (Request $request, Response $response) use ($db, $sendJsonResponse) {
        /*
         * NOTE DE SÉCURITÉ: $data['player_id'] devrait être extrait d'un JWT validé.
         */
        $data = $request->getParsedBody();
        $player_id = $data['player_id'] ?? null;

        if (!$player_id) {
            return $sendJsonResponse($response, ['error' => 'ID joueur manquant.'], 400);
        }

        $stmt = $db->prepare("UPDATE participants SET is_ready = TRUE WHERE id = ?");
        $stmt->execute([$player_id]);

        // Vérifier si l'update a eu lieu (le participant existe)
        if ($stmt->rowCount() === 0) {
             return $sendJsonResponse($response, ['error' => 'Participant non trouvé.'], 404);
        }

        return $sendJsonResponse($response, ['success' => true, 'message' => 'Statut prêt mis à jour.']);
    });

    $app->get('/api/players/ready-list', function (Request $request, Response $response) use ($db, $sendJsonResponse) {
        $stmt = $db->query("SELECT pseudo, is_admin, is_ready FROM participants ORDER BY id ASC");
        $players = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // Assurez-vous que is_admin et is_ready sont bien des booléens
        $players = array_map(function($player) {
            $player['is_admin'] = (bool)$player['is_admin'];
            $player['is_ready'] = (bool)$player['is_ready'];
            return $player;
        }, $players);

        return $sendJsonResponse($response, $players);
    });

    $app->post('/api/game/start', function (Request $request, Response $response) use ($db, $sendJsonResponse, $checkAdminAccess) {
        /*
         * NOTE DE SÉCURITÉ: $data['admin_id'] devrait être extrait d'un JWT validé.
         */
        $data = $request->getParsedBody();
        $admin_id = $data['admin_id'] ?? null;
        
        // ** Utilisation de la nouvelle fonction utilitaire pour vérifier l'accès admin **
        $accessCheck = $checkAdminAccess((int)$admin_id, $db, $response, $sendJsonResponse);
        if ($accessCheck !== null) {
            return $accessCheck; // Renvoie l'erreur 400 ou 403
        }

        // Lancement du jeu
        $db->query("UPDATE participants SET game_started = TRUE");

        return $sendJsonResponse($response, ['message' => 'Partie lancée !']);
    });

    $app->get('/api/game/status', function (Request $request, Response $response) use ($db, $sendJsonResponse) {
        // On récupère le statut global
        $stmt = $db->query("SELECT game_started FROM participants LIMIT 1");
        $status = $stmt->fetchColumn();

        // Conversion en booléen
        $started = ($status === 't' || $status === true);

        return $sendJsonResponse($response, ['started' => $started]);
    });

    // ===============================
    // 🧹 Route : Réinitialisation du jeu (pour l'admin)
    // ===============================
    $app->post('/api/game/reset', function (Request $request, Response $response) use ($db, $sendJsonResponse, $checkAdminAccess) {
        /*
         * NOTE DE SÉCURITÉ: $data['admin_id'] devrait être extrait d'un JWT validé.
         */
        $data = $request->getParsedBody();
        $admin_id = $data['admin_id'] ?? null; 

        // ** Utilisation de la nouvelle fonction utilitaire pour vérifier l'accès admin **
        $accessCheck = $checkAdminAccess((int)$admin_id, $db, $response, $sendJsonResponse);
        if ($accessCheck !== null) {
            return $accessCheck; // Renvoie l'erreur 400 ou 403
        }

        try {
            // Réinitialiser le statut 'is_used' des questions
            $db->query("UPDATE questions SET is_used = FALSE"); 
            
            // Réinitialiser les scores, le statut 'ready' et 'game_started' des participants
            $db->query("UPDATE participants SET score = 0, is_ready = FALSE, game_started = FALSE"); 

            return $sendJsonResponse($response, ['message' => 'Le jeu a été complètement réinitialisé. Les questions peuvent être réutilisées.'], 200);

        } catch (\Exception $e) {
            error_log("Erreur lors de la réinitialisation du jeu: " . $e->getMessage());
            return $sendJsonResponse($response, ['error' => 'Erreur serveur interne lors de la réinitialisation.'], 500);
        }
    });

    // --- Ajout de la route de test DB pour la vérification initiale ---
    $app->get('/api/test-db', function (Request $request, Response $response) use ($db, $sendJsonResponse) {
        try {
            $stmt = $db->query("SELECT version()");
            $version = $stmt->fetchColumn();
            return $sendJsonResponse($response, [
                'status' => 'success',
                'message' => 'Connexion PostgreSQL réussie !',
                'db_version' => $version
            ], 200);
        } catch (\PDOException $e) {
            return $sendJsonResponse($response, [
                'status' => 'error',
                'message' => 'Échec de la connexion à PostgreSQL: ' . $e->getMessage()
            ], 500);
        }
    });
};
