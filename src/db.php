<?php

/**
 * Connexion universelle à la base de données :
 * - Utilise PostgreSQL sur Heroku (via DATABASE_URL)
 * - Utilise SQLite en local si aucune variable DATABASE_URL n’est trouvée
 */

$db = null;

try {
    // ---------------------------------------------------------------------
    // 🔹 1. Connexion Heroku PostgreSQL
    // ---------------------------------------------------------------------
    if (getenv('DATABASE_URL')) {
        // Exemple : postgres://user:password@host:port/dbname
        $url = parse_url(getenv('DATABASE_URL'));

        $host = $url['host'];
        $port = $url['port'];
        $user = $url['user'];
        $pass = $url['pass'];
        $dbname = ltrim($url['path'], '/');

        $dsn = "pgsql:host=$host;port=$port;dbname=$dbname;sslmode=require";

        $db = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }
    // ---------------------------------------------------------------------
    // 🔹 2. Sinon, fallback local SQLite
    // ---------------------------------------------------------------------
    else {
        $db_file = __DIR__ . '/../quiz.sqlite';
        $db = new PDO("sqlite:$db_file");
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

} catch (PDOException $e) {
    http_response_code(500);
    echo "<h1>Erreur Critique de Base de Données</h1>";
    echo "<p>Connexion échouée : " . htmlspecialchars($e->getMessage()) . "</p>";
    exit();
}

?>
