<?php
// src/db.php

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->safeLoad();

try {
    $host = getenv('DB_HOST') ?: 'quiz-db'; // Docker ou Render
    $port = getenv('DB_PORT') ?: 3306;
    $dbname = getenv('DB_NAME') ?: 'quiz_game';
    $user = getenv('DB_USER') ?: 'root';
    $pass = getenv('DB_PASSWORD') ?: 'root';

    $dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";
    $pdo = new PDO($dsn, $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    error_log("Connexion BDD réussie.");
} catch (PDOException $e) {
    error_log("Erreur connexion BDD : " . $e->getMessage());
    http_response_code(500);
    die("Erreur de connexion à la base de données.");
}

// On retourne $pdo pour l'inclure ailleurs
return $pdo;
