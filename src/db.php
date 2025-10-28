<?php

/**
 * db.php
 * Gère la connexion à la base de données (PostgreSQL sur Heroku, SQLite en local).
 */

$db = null;

try {
    // --- 1️⃣ Vérifier si on est sur Heroku (PostgreSQL) ---
    if (getenv('DATABASE_URL')) {
        $url = parse_url(getenv('DATABASE_URL'));

        $host = $url['host'];
        $port = $url['port'];
        $user = $url['user'];
        $pass = $url['pass'];
        $dbname = ltrim($url['path'], '/');

        // Connexion PostgreSQL
        $dsn = "pgsql:host=$host;port=$port;dbname=$dbname;user=$user;password=$pass;sslmode=require";
        $db = new PDO($dsn);

        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    } 
    // --- 2️⃣ Sinon, on est en local (SQLite par défaut) ---
    else {
        $db_file = __DIR__ . '/../quiz.sqlite';
        $db = new PDO("sqlite:$db_file");
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

} catch (PDOException $e) {
    http_response_code(500);
    echo "<h1>Erreur de connexion à la base de données</h1>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
    exit();
}
