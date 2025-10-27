<?php
// src/db.php

// Chargement .env si tu l'utilises (optionnel, pour le développement local)
// Assurez-vous d'avoir installé 'vlucas/phpdotenv' si vous utilisez .env
if (file_exists(__DIR__ . '/../.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
    $dotenv->load();
}

// --- LOGIQUE DE CONNEXION HEROKU / LOCAL ---

// 1. Tenter la connexion via DATABASE_URL (Heroku PostgreSQL)
$databaseUrl = $_ENV['DATABASE_URL'] ?? null;

if ($databaseUrl) {
    // Si Heroku a fourni une URL, on utilise PostgreSQL
    
    // Parse l'URL (Ex: postgres://user:pass@host:port/dbname)
    $config = parse_url($databaseUrl);

    $host = $config['host'];
    $port = $config['port'] ?? 5432; // Le port par défaut de PostgreSQL est 5432
    $db = trim($config['path'], '/');
    $user = $config['user'];
    $pass = $config['pass'];
    
    // Le DSN pour PostgreSQL
    $dsn = "pgsql:host=$host;port=$port;dbname=$db;user=$user;password=$pass";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

} else {
    // 2. Fallback pour la connexion MySQL locale
    
    $host = $_ENV['DB_HOST'] ?? '127.0.0.1';
    $db   = $_ENV['DB_NAME'] ?? 'quiz_game'; // Votre nom de BDD local
    $user = $_ENV['DB_USER'] ?? 'root';     // Votre utilisateur local
    $pass = $_ENV['DB_PASS'] ?? '';         // Votre mot de passe local
    $charset = 'utf8mb4';

    // Le DSN pour MySQL
    $dsn = "mysql:host=$host;dbname=$db;charset=$charset";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ];
}


// --- Établissement de la Connexion ---
try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    // Si la connexion échoue (local ou Heroku)
    // IMPORTANT : Ne pas exposer $e->getMessage() en production pour la sécurité
    error_log("Database connection failed: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(["error" => "Internal server error: Database connection failed."]);
    exit();
}
