<?php

// Fichier de connexion à la base de données (PostgreSQL via PDO)

/**
 * Fonction pour obtenir l'instance de connexion PDO.
 *
 * @return PDO L'objet de connexion PDO.
 * @throws Exception En cas d'échec de la connexion.
 */
function getDatabaseConnection(): PDO
{
    // Tente de récupérer l'URL de la base de données fournie par l'environnement d'hébergement (ex: Heroku)
    $databaseUrl = getenv('DATABASE_URL');

    // -------------------------------------------------------------------------
    // 1. Connexion PostgreSQL (Environnement d'hébergement, ex: Heroku)
    // -------------------------------------------------------------------------
    if ($databaseUrl) {
        try {
            // CORRECTION CRITIQUE POUR HEROKU/Vercel :
            // parse_url() ne gère pas toujours le schéma 'postgres://' correctement.
            // On le remplace par 'https://' pour garantir que toutes les parties (user, pass, port) sont analysées.
            $parsedDbUrl = str_replace(['postgres://', 'postgresql://'], 'https://', $databaseUrl);

            // Analyse de l'URL pour extraire les informations de connexion
            $urlParts = parse_url($parsedDbUrl);

            // Vérification des parties essentielles
            if (!$urlParts || !isset($urlParts['host'], $urlParts['user'], $urlParts['pass'])) {
                error_log("Erreur: Impossible d'analyser l'URL de la base de données ou les composants sont manquants.");
                throw new Exception("Format d'URL de base de données invalide.");
            }

            $host = $urlParts['host'];
            $port = $urlParts['port'] ?? 5432;
            $user = $urlParts['user'];
            $password = $urlParts['pass'];
            // Le chemin (path) est le nom de la base de données. On retire le '/' initial.
            $path = ltrim($urlParts['path'], '/');
            $sslMode = 'require'; // Mode SSL obligatoire pour Heroku/Vercel

            // Construction du DSN pour PostgreSQL
            $dsn = "pgsql:host=$host;port=$port;dbname=$path;sslmode=$sslMode";

            // Options PDO spécifiques à PostgreSQL
            $options = [
                // Gestion des erreurs : lancer une exception en cas d'erreur
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                // Mode de récupération par défaut : tableaux associatifs
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                // DÉSACTIVE l'émulation des requêtes préparées (CRUCIAL pour PostgreSQL)
                PDO::ATTR_EMULATE_PREPARES => false,
            ];

            $pdo = new PDO($dsn, $user, $password, $options);
            return $pdo;

        } catch (Exception $e) {
            // Enregistrement de l'erreur dans les logs Heroku (critique pour le débogage)
            error_log("Erreur de connexion PostgreSQL: " . $e->getMessage());
            throw new Exception("Échec de la connexion à la base de données PostgreSQL.");
        }
    }

    // -------------------------------------------------------------------------
    // 2. Connexion MySQL (Environnement de développement local)
    // -------------------------------------------------------------------------
    else {
        // Paramètres pour l'environnement local (à adapter si nécessaire)
        $host = 'localhost';
        $dbname = 'quiz_game'; 
        $user = 'root';
        $password = ''; 
        
        try {
            // Construction du DSN pour MySQL
            $dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";
            
            // Options PDO classiques
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];

            $pdo = new PDO($dsn, $user, $password, $options);
            return $pdo;

        } catch (PDOException $e) {
            error_log("Erreur de connexion MySQL: " . $e->getMessage());
            throw new Exception("Échec de la connexion à la base de données MySQL.");
        }
    }
}

// Globalisation de la connexion pour une utilisation facile dans d'autres fichiers
try {
    $db = getDatabaseConnection();
} catch (Exception $e) {
    // Affichage d'un message d'erreur clair si la connexion échoue
    http_response_code(500);
    echo "<h1>Erreur Serveur</h1>";
    echo "<p>Impossible de se connecter à la base de données. Détail: " . $e->getMessage() . "</p>";
    // Arrêter l'exécution du script
    exit(); 
}

// $db est désormais l'instance PDO, disponible globalement.
