<?php

// Fichier de connexion à la base de données (MySQL ou PostgreSQL via PDO)

/**
 * Fonction pour obtenir l'instance de connexion PDO.
 *
 * Cette fonction détecte si l'application est déployée (en vérifiant DATABASE_URL)
 * pour utiliser PostgreSQL ou si elle est en local pour utiliser MySQL.
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
            // Analyse de l'URL pour extraire les informations de connexion
            $urlParts = parse_url($databaseUrl);

            $host = $urlParts['host'];
            $port = $urlParts['port'] ?? 5432;
            $user = $urlParts['user'];
            $password = $urlParts['pass'];
            $path = ltrim($urlParts['path'], '/');
            $sslMode = 'require'; // Mode SSL recommandé pour les environnements cloud

            // Construction du DSN pour PostgreSQL
            $dsn = "pgsql:host=$host;port=$port;dbname=$path;sslmode=$sslMode";

            // Options PDO spécifiques à PostgreSQL
            $options = [
                // Gestion des erreurs : lancer une exception en cas d'erreur
                PDO::ATTR_ERRMODE           => PDO::ERRMODE_EXCEPTION,
                // Mode de récupération par défaut : tableaux associatifs
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                // DÉSACTIVE l'émulation des requêtes préparées (CRUCIAL pour PostgreSQL avec '?' comme placeholder)
                PDO::ATTR_EMULATE_PREPARES  => false,
            ];

            $pdo = new PDO($dsn, $user, $password, $options);
            return $pdo;

        } catch (PDOException $e) {
            // Enregistrement de l'erreur (pour les logs, pas pour l'utilisateur)
            error_log("Erreur de connexion PostgreSQL: " . $e->getMessage());
            // Lancer une exception générique pour le reste de l'application
            throw new Exception("Échec de la connexion à la base de données PostgreSQL.");
        }
    }

    // -------------------------------------------------------------------------
    // 2. Connexion MySQL (Environnement de développement local)
    // -------------------------------------------------------------------------
    else {
        // Paramètres pour l'environnement local (à adapter si nécessaire)
        $host = 'localhost';
        $dbname = 'votre_nom_de_base'; 
        $user = 'root';
        $password = ''; 
        
        try {
            // Construction du DSN pour MySQL
            $dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";
            
            // Options PDO classiques
            $options = [
                PDO::ATTR_ERRMODE           => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES  => false,
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
    echo "<p>Impossible de se connecter à la base de données. Veuillez vérifier vos logs.</p>";
    // Arrêter l'exécution du script
    exit(); 
}

// Maintenant, dans n'importe quel autre fichier PHP, vous pouvez simplement utiliser $db
// pour accéder à l'instance PDO (ex: $db->prepare('...'))

?>
