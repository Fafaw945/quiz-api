<?php
/**
 * Point d'entrée principal de l'application.
 */

// 1. DÉFINIR LA RACINE DE L'APPLICATION
// Ceci pointe vers le dossier QUIZ-API/ (le parent de public/)
$app_root = __DIR__ . '/..'; 


// -------------------------------------------------------------------------
// VÉRIFICATION CRITIQUE D'ENVIRONNEMENT
// -------------------------------------------------------------------------
// ... (Laissez votre code de vérification pdo_pgsql / pdo_mysql inchangé)
// ...

// -------------------------------------------------------------------------
// INITIALISATION DE LA BASE DE DONNÉES
// -------------------------------------------------------------------------

// CHEMIN CORRIGÉ pour db.php
require_once $app_root . '/src/db.php';
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

// LIGNE RETIRÉE : require_once 'src/functions.php';

// Commencer le tampon de sortie pour capturer le contenu de la vue
ob_start();

// Déterminer la vue à charger (CHEMINS CORRIGÉS)
switch ($route) {
    case 'home':
        require $app_root . '/views/home.php';
        break;
    case 'quiz':
        // Pour l'instant, c'est juste la vue du quiz
        require $app_root . '/views/quiz.php';
        break;
    case 'result':
        require $app_root . '/views/result.php';
        break;
    default:
        http_response_code(404);
        require $app_root . '/views/404.php';
        break;
}

// Récupérer le contenu du tampon de sortie
$content = ob_get_clean();

// Charger le layout principal et y injecter le contenu (CHEMIN CORRIGÉ)
require $app_root . '/views/layout.php';
?>