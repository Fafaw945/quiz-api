<?php

/**
 * Ce fichier gère la connexion à la base de données PostgreSQL
 * en utilisant la variable d'environnement DATABASE_URL fournie par Heroku.
 * * La variable de connexion $db est rendue disponible à la fin du script.
 */

$db = null;

// 1. Récupération de la variable d'environnement DATABASE_URL
$db_url = getenv('DATABASE_URL');

if (!$db_url) {
    // Cas où la variable Heroku n'est pas définie (devrait être impossible en prod)
    http_response_code(500);
    error_log("FATAL: DATABASE_URL variable d'environnement non trouvée.");
    echo "<h1>Erreur de Configuration de Base de Données</h1>";
    echo "<p>La variable de connexion (DATABASE_URL) est manquante.</p>";
    exit();
}

try {
    // 2. Analyse (parsing) de l'URL de connexion PostgreSQL
    $url = parse_url($db_url);

    $host = $url['host'];
    $port = $url['port'] ?? 5432; // Port par défaut PostgreSQL
    $dbname = substr($url['path'], 1); // Enlève le '/' au début du chemin
    $user = $url['user'];
    $password = $url['pass'];

    // 3. Construction du DSN (Data Source Name) pour PDO PostgreSQL
    $dsn = "pgsql:host={$host};port={$port};dbname={$dbname}";

    // 4. Connexion à la base de données
    $db = new PDO($dsn, $user, $password);
    
    // 5. Configuration de PDO pour lever des exceptions en cas d'erreur SQL
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Si le code atteint ce point, la connexion $db est réussie.
    
} catch (PDOException $e) {
    // Gérer l'échec de la connexion à la BDD (mauvais mot de passe, hôte injoignable, etc.)
    http_response_code(500);
    error_log("PostgreSQL Connection Failed: " . $e->getMessage());
    echo "<h1>Erreur Critique de Base de Données</h1>";
    // Pour le débogage, vous pouvez afficher l'erreur, mais attention en production.
    echo "<p>Échec de la connexion PostgreSQL. Vérifiez les logs pour plus de détails.</p>";
    exit();
}

// La variable $db est maintenant l'objet PDO connecté à PostgreSQL, prêt à l'emploi.

?>