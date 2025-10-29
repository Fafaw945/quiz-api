<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;

require __DIR__ . '/../vendor/autoload.php';

error_log("INDEX.PHP start");

$app = AppFactory::create();

// ===============================
// 🔧 1. Connexion BDD (Adaptée à Heroku - CORRIGÉ PGSQL)
// ===============================

$dbUrl = getenv('DATABASE_URL');
$pdo = null;

if ($dbUrl) {
    // Remplacer 'postgres://' par 'pgsql://' pour la compatibilité PDO
    $dbUrl = str_replace("postgres://", "pgsql://", $dbUrl);
    
    $dbParams = parse_url($dbUrl);

    // Définition du DSN pour PostgreSQL (pgsql)
    $dsn = sprintf(
        'pgsql:host=%s;port=%s;dbname=%s;user=%s;password=%s',
        $dbParams['host'],
        $dbParams['port'],
        ltrim($dbParams['path'], '/'),
        $dbParams['user'],
        $dbParams['pass']
    );

    try {
        $pdo = new PDO($dsn);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        // Exposer $pdo globalement pour les routes
        $GLOBALS['db'] = $pdo; 
        error_log("DB Connection successful with PGSQL.");

    } catch (PDOException $e) {
        error_log("DB CONNECTION ERROR: " . $e->getMessage());
        // En cas d'échec de connexion BDD, on arrête l'application.
        http_response_code(500);
        die("Erreur de connexion à la base de données PostgreSQL.");
    }

} else {
    // Fallback local (si vous n'avez pas DATABASE_URL, utiliser MySQL par défaut)
    error_log("DATABASE_URL not set. Using local MySQL fallback.");
    $pdo = new PDO('mysql:host=localhost;dbname=quiz_game;charset=utf8', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $GLOBALS['db'] = $pdo;
}

// ===============================
// 🌍 2. Middleware CORS
// ===============================
$app->add(function (Request $request, $handler) {
    $response = $handler->handle($request);

    // Liste des origines autorisées
    $allowedOrigins = [
        'https://quiz-app-eight-gold-57.vercel.app',
        // Note: L'URL Heroku de l'API doit être utilisée par le frontend, pas par le backend
        'http://localhost:3000' 
    ];
    
    $origin = $request->getHeaderLine('Origin');
    $allowedOrigin = '*'; // Par défaut, autorise tout

    if (in_array($origin, $allowedOrigins)) {
        $allowedOrigin = $origin;
    }

    return $response
        ->withHeader('Access-Control-Allow-Origin', $allowedOrigin)
        ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization')
        ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, PATCH, OPTIONS')
        ->withHeader('Access-Control-Allow-Credentials', 'true'); // Ajout pour les cookies/sessions
});

// ⚙️ Préflight OPTIONS
$app->options('/{routes:.+}', function (Request $request, Response $response) {
    return $response->withStatus(200);
});

// ===============================
// 🔹 Middleware Slim 4 requis
// ===============================
$app->addRoutingMiddleware();
$app->addBodyParsingMiddleware();

// ===============================
// 🔹 3. Inclure les routes
// ===============================
$routesFile = __DIR__ . '/../routes.php';
if (!file_exists($routesFile)) {
    die("ERREUR: Le fichier de routes est introuvable à l'emplacement: " . $routesFile);
}
require $routesFile;

// 🚀 Lancer l’application
$app->run();
