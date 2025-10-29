<?php

/**
 * Gère la connexion à la base de données PostgreSQL en utilisant la variable d'environnement DATABASE_URL.
 * Ce fichier est minimal et ne tente aucune création de table.
 */

$db = null;
$databaseUrl = getenv('DATABASE_URL');

try {
    if ($databaseUrl) {
        // Heroku utilise le schéma 'postgres://', mais PDO PostgreSQL attend 'pgsql://'
        $databaseUrl = str_replace("postgres://", "pgsql://", $databaseUrl);
        
        // Parse l'URL pour extraire les composants de connexion
        $dbParts = parse_url($databaseUrl);
        
        if ($dbParts === false || !isset($dbParts['host'], $dbParts['user'], $dbParts['pass'], $dbParts['path'], $dbParts['port'])) {
             // Si l'URL n'est pas parsable, on lève une exception.
             throw new Exception("DATABASE_URL est présente mais invalide.");
        }
        
        $host = $dbParts['host'];
        $port = $dbParts['port'];
        $user = $dbParts['user'];
        $password = $dbParts['pass'];
        // Le path contient le nom de la base de données (e.g., /dbname)
        $dbname = ltrim($dbParts['path'], '/');

        $dsn = "pgsql:host=$host;port=$port;dbname=$dbname;user=$user;password=$password";
        
        // Tentative de connexion à la base de données PostgreSQL
        $db = new PDO($dsn);

    } else {
        // Fallback local (ne sera pas utilisé sur Heroku)
        $dsn = 'mysql:host=localhost;dbname=quizdb';
        $db = new PDO($dsn, 'root', 'root');
    }
    
    // Configurer PDO pour lever des exceptions en cas d'erreur SQL
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    // Si la connexion réussit, $db contient l'objet de connexion PDO.

} catch (PDOException $e) {
    // En cas d'échec de connexion (timeout ou mauvais identifiants), on affiche le message d'erreur.
    http_response_code(500);
    echo "<h1>Erreur Critique de Connexion BDD</h1>";
    echo "<p>La connexion à la base de données a échoué. Vérifiez DATABASE_URL et ses identifiants.</p>";
    error_log("DB Connection Failed: " . $e->getMessage()); // Log l'erreur complète sur Heroku
    exit();
} catch (Exception $e) {
    http_response_code(500);
    echo "<h1>Erreur Critique du Serveur</h1>";
    echo "<p>Erreur interne : " . htmlspecialchars($e->getMessage()) . "</p>";
    exit();
}

?>
