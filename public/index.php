<?php

/**
 * Point d'entrée principal de l'application.
 * Gère la navigation et le rendu des vues.
 */

// -------------------------------------------------------------------------
// VÉRIFICATION CRITIQUE D'ENVIRONNEMENT
// -------------------------------------------------------------------------

// Si nous sommes dans un environnement d'hébergement (Heroku/Vercel)
if (getenv('DATABASE_URL')) {
    // Vérifier si l'extension PDO PostgreSQL est chargée
    if (!extension_loaded('pdo_pgsql')) {
        http_response_code(500);
        echo "<h1>Erreur de Configuration PHP</h1>";
        echo "<p>L'extension PDO pour PostgreSQL (pdo_pgsql) n'est pas chargée. Veuillez vous assurer que l'environnement PHP la prend en charge.</p>";
        exit();
    }
} else {
    // Vérifier si l'extension PDO MySQL est chargée pour le développement local
    if (!extension_loaded('pdo_mysql')) {
        // Optionnel : Vous pourriez préférer pdo_sqlite si vous n'avez pas de MySQL local.
        http_response_code(500);
        echo "<h1>Erreur de Configuration PHP</h1>";
        echo "<p>L'extension PDO pour MySQL (pdo_mysql) n'est pas chargée. Vous devez l'activer pour l'exécution locale.</p>";
        exit();
    }
}

// -------------------------------------------------------------------------
// INITIALISATION DE LA BASE DE DONNÉES
// -------------------------------------------------------------------------

// Les chemins sont relatifs à l'emplacement de index.php (racine)
require_once '/../src/db.php'; 
// La variable $db est maintenant disponible pour les requêtes.


// -------------------------------------------------------------------------
// LOGIQUE DE ROUTAGE ET CONTRÔLEUR
// -------------------------------------------------------------------------

// Détermine la route demandée par l'utilisateur
$route = $_GET['page'] ?? 'home'; 

// Démarrer la session
session_start();

// -------------------------------------------------------------------------
// VUES
// -------------------------------------------------------------------------

// Inclusion des fonctions utilitaires (comme la navigation)
require_once 'src/functions.php';

// Commencer le tampon de sortie pour capturer le contenu de la vue
ob_start();

// Déterminer la vue à charger
switch ($route) {
    case 'home':
        require 'views/home.php';
        break;
    case 'quiz':
        // Pour l'instant, c'est juste la vue du quiz
        require 'views/quiz.php';
        break;
    case 'result':
        require 'views/result.php';
        break;
    default:
        http_response_code(404);
        require 'views/404.php';
        break;
}

// Récupérer le contenu du tampon de sortie
$content = ob_get_clean();

// Charger le layout principal et y injecter le contenu
require 'views/layout.php';

?>
