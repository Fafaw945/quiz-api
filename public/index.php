<?php 

use Psr\Http\Message\ResponseInterface as Response; 
use Psr\Http\Message\ServerRequestInterface as Request; 
use Slim\Factory\AppFactory; 
use Exception;
use PDO;

// Définit le chemin absolu vers le répertoire parent (QUIZ-API/)
$app_root = __DIR__ . '/..';
require $app_root . '/vendor/autoload.php';

// Création de l'application Slim
$app = AppFactory::create(); 

// Définir $pdo en dehors de la portée de Slim pour qu'elle soit visible partout
global $pdo; 

// =============================== 
// 🔧 1. Connexion BDD (Adaptée à Heroku PostgreSQL)
// =============================== 

try {
    $dbUrl = getenv("DATABASE_URL");
    if (!$dbUrl) {
        die("Erreur critique: La variable d'environnement DATABASE_URL est manquante.");
    }

    $dbParams = parse_url($dbUrl);

    if ($dbParams === false || !isset($dbParams['scheme']) || $dbParams['scheme'] !== 'postgres') {
        throw new Exception("L'URL de la base de données n'est pas valide ou n'utilise pas le schéma 'postgres'.");
    }

    // Construction de la DSN pour PostgreSQL
    $dsn = sprintf(
        'pgsql:host=%s;port=%s;dbname=%s;user=%s;password=%s',
        $dbParams['host'],
        $dbParams['port'] ?? 5432, 
        ltrim($dbParams['path'], '/'),
        $dbParams['user'],
        $dbParams['pass']
    );

    // Initialisation de l'objet PDO et affectation à la variable globale
    $pdo = new PDO($dsn); 
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); 
    
} catch (Exception $e) {
    http_response_code(500);
    // Erreur critique de connexion
    die("Erreur de connexion à la base de données: " . $e->getMessage()); 
}

// ===============================
// 💡 Fonction utilitaire pour le formatage JSON (Globale)
// ===============================
if (!function_exists('sendJsonResponse')) {
    function sendJsonResponse(Response $response, array $data, int $status = 200): Response {
        $response->getBody()->write(json_encode($data));
        return $response->withHeader('Content-Type', 'application/json')->withStatus($status);
    }
}


// =============================== 
// 🌍 2. Middleware CORS (Simplifié) 
// =============================== 
// Gère la requête OPTIONS pour le pré-vol CORS
$app->options('/{routes:.+}', function (Request $request, Response $response) { 
    return $response->withStatus(200); 
}); 

$app->addRoutingMiddleware();

// =============================== 
// 🔹 3. Inclure et Exécuter les routes
// =============================== 
$routesFile = $app_root . '/src/routes.php'; 
if (!file_exists($routesFile)) {
    die("ERREUR: Le fichier de routes est introuvable à l'emplacement: " . $routesFile);
}
// Le fichier routes.php retourne une fonction qui reçoit l'objet $app
$routes = require $routesFile;
// On passe l'objet $app. La connexion $pdo sera récupérée via `global $pdo;` dans routes.php
$routes($app);

// 🚀 Lancer l’application 
$app->run();
