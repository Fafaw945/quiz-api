<?php

namespace Src\Handlers;

use PDO;
use PDOException;

/**
 * Gère la connexion unique à la base de données PostgreSQL.
 */
class DatabaseHandler
{
    private ?PDO $connection = null;

    // ATTENTION : VALEURS FOURNIES PAR L'UTILISATEUR (CONNEXION AWS RDS)
    private string $host = 'c8ie82co1njm86.cluster-czz5s0kz4scl.eu-west-1.rds.amazonaws.com'; 
    private string $db = 'dainl179p8nlrp'; 
    private string $user = 'u333hkbussc3oi'; 
    private string $pass = 'pc8a8c7fe39db02d539be3ef6f8e3508d4c77a01f9e86b4f2cd03558f9bcfe765'; 
    private int $port = 5432; // Le port par défaut est confirmé

    public function getConnection(): PDO
    {
        if ($this->connection === null) {
            $dsn = "pgsql:host={$this->host};port={$this->port};dbname={$this->db}";
            try {
                $this->connection = new PDO($dsn, $this->user, $this->pass, [
                    // Configuration pour gérer les erreurs et le mode de récupération
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ]);
            } catch (PDOException $e) {
                // En cas d'échec de la connexion, nous enregistrons l'erreur (pour le débogage)
                error_log("DB Connection Error: " . $e->getMessage());
                // Et levons une exception générique
                throw new PDOException("Impossible de se connecter à la base de données. Vérifiez les informations de connexion.");
            }
        }
        return $this->connection;
    }
}
