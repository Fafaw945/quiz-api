<?php

/**
 * Ce fichier gère la connexion à la base de données.
 * Nous passons à SQLite pour garantir la compatibilité dans les environnements restreints (sandbox).
 */

$db = null;

try {
    // Chemin vers le fichier SQLite. Il sera créé s'il n'existe pas.
    $db_file = 'quiz.sqlite';
    
    // Connexion à SQLite
    // Note: PDO est généralement intégré, donc cette méthode fonctionne souvent quand les autres échouent.
    $db = new PDO("sqlite:$db_file");
    
    // Configurer PDO pour lever des exceptions en cas d'erreur SQL, ce qui est essentiel pour le débogage.
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // -----------------------------------------------------------
    // INITIALISATION DE LA BASE DE DONNÉES (Création des tables)
    // -----------------------------------------------------------
    
    // 1. Création de la table 'quizzes'
    $db->exec("
        CREATE TABLE IF NOT EXISTS quizzes (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            description TEXT
        )
    ");

    // 2. Création de la table 'questions'
    $db->exec("
        CREATE TABLE IF NOT EXISTS questions (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            quiz_id INTEGER NOT NULL,
            question_text TEXT NOT NULL,
            FOREIGN KEY (quiz_id) REFERENCES quizzes(id) ON DELETE CASCADE
        )
    ");

    // 3. Création de la table 'answers'
    $db->exec("
        CREATE TABLE IF NOT EXISTS answers (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            question_id INTEGER NOT NULL,
            answer_text TEXT NOT NULL,
            is_correct BOOLEAN NOT NULL DEFAULT 0,
            FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE CASCADE
        )
    ");

    // -----------------------------------------------------------
    // AJOUT DE DONNÉES DE TEST (si la table est vide)
    // -----------------------------------------------------------
    
    // Compter le nombre d'entrées
    $stmt = $db->query("SELECT COUNT(*) FROM quizzes");
    $count = $stmt->fetchColumn();

    if ($count == 0) {
        // Insertion du quiz de test
        $db->exec("INSERT INTO quizzes (name, description) VALUES ('Quiz de Démarrage', 'Un petit test pour vérifier que tout fonctionne.')");
        $quiz_id = $db->lastInsertId();

        // Insertion des questions et réponses
        $db->exec("INSERT INTO questions (quiz_id, question_text) VALUES ($quiz_id, 'Quelle est la capitale de la France ?')");
        $q1_id = $db->lastInsertId();
        
        $db->exec("INSERT INTO answers (question_id, answer_text, is_correct) VALUES ($q1_id, 'Marseille', 0)");
        $db->exec("INSERT INTO answers (question_id, answer_text, is_correct) VALUES ($q1_id, 'Paris', 1)");
        $db->exec("INSERT INTO answers (question_id, answer_text, is_correct) VALUES ($q1_id, 'Lyon', 0)");

        $db->exec("INSERT INTO questions (quiz_id, question_text) VALUES ($quiz_id, 'Quelle est la meilleure langue de programmation ?')");
        $q2_id = $db->lastInsertId();
        
        $db->exec("INSERT INTO answers (question_id, answer_text, is_correct) VALUES ($q2_id, 'PHP', 1)");
        $db->exec("INSERT INTO answers (question_id, answer_text, is_correct) VALUES ($q2_id, 'Python', 0)");
        $db->exec("INSERT INTO answers (question_id, answer_text, is_correct) VALUES ($q2_id, 'JavaScript', 0)");
    }

} catch (PDOException $e) {
    // Si même la connexion à SQLite échoue, il y a un problème plus fondamental avec PHP.
    http_response_code(500);
    // On affiche l'erreur détaillée pour le débogage.
    echo "<h1>Erreur Critique de Base de Données</h1>";
    echo "<p>La connexion à SQLite a échoué. Cause : " . htmlspecialchars($e->getMessage()) . "</p>";
    exit();
}

?>
