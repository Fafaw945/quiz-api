<?php

namespace App\Handlers;

use PDO;
use Exception;

/**
 * Gère la connexion à la base de données.
 * Cette version utilise une connexion PDO fournie de l'extérieur 
 * (par exemple depuis public/index.php pour Heroku/Environnements spécifiques).
 */
class DatabaseHandler
{
    private ?PDO $pdo = null;

    /**
     * Accepte une instance PDO ou gère la connexion via DATABASE_URL si aucune n'est fournie.
     * Pour une configuration Slim simple comme la vôtre, il est plus simple de lui passer 
     * la connexion déjà établie.
     */
    public function __construct(?PDO $pdoInstance = null)
    {
        if ($pdoInstance) {
            $this->pdo = $pdoInstance;
        } else {
            $this->connect(); // Tentative de connexion si non fournie (fallback)
        }
    }

    /**
     * Établit la connexion si elle n'a pas été fournie ou n'existe pas (logique du fichier index.php)
     */
    private function connect(): void
    {
        try {
            $dbUrl = getenv('DATABASE_URL');
            if (!$dbUrl) {
                throw new Exception("La variable d'environnement DATABASE_URL est manquante.");
            }
            
            // Logique de parse_url pour PostgreSQL (reprise de votre index.php)
            $dbParams = parse_url($dbUrl);

            $dsn = sprintf(
                'pgsql:host=%s;port=%s;dbname=%s;user=%s;password=%s',
                $dbParams['host'],
                $dbParams['port'] ?? 5432, 
                ltrim($dbParams['path'], '/'),
                $dbParams['user'],
                $dbParams['pass']
            );
            
            $this->pdo = new PDO($dsn);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); 

        } catch (Exception $e) {
            error_log("DB Error in DatabaseHandler: " . $e->getMessage());
            throw new Exception("Erreur critique de connexion à la base de données.", 500, $e);
        }
    }

    /**
     * Retourne l'instance PDO de la connexion à la base de données.
     */
    public function getConnection(): PDO
    {
        if ($this->pdo === null) {
            // Devrait être déjà connecté dans le constructeur, mais sécurité
            $this->connect(); 
        }
        return $this->pdo;
    }
}
