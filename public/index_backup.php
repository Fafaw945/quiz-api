<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;

require __DIR__ . '/../vendor/autoload.php';

error_log("INDEX.PHP start");

$app = AppFactory::create();

// ===============================
// 🔧 1. Connexion BDD (Adaptée à Heroku)
// ===============================

$dbUrl = getenv('DATABASE_URL');
if (!$dbUrl) {
    // Fallback local (développement)
    $dbUrl = "mysql://root:@localhost:3306/quiz_game";
}

$dbParams = parse_url($dbUrl);

$dsn = sprintf(
    'mysql:host=%s;dbname=%s;charset=utf8',
    $dbParams['host'],
    ltrim($dbParams['path'], '/')
);

$pdo = new PDO(
    $dsn,
    $dbParams['user'] ?? null,
    $dbParams['pass'] ?? null
);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// ===============================
// 🌍 2. Middleware CORS
// ===============================
$app->add(function (Request $request, $handler) {
    $response = $handler->handle($request);

    $vercelOrigin = 'https://quiz-app-eight-gold-57.vercel.app';
    $herokuOrigin = 'https://quiz-api-fafaw945-13ff0b479a67.herokuapp.com';
    $origin = $request->getHeaderLine('Origin');

    if ($origin === $vercelOrigin || $origin === $herokuOrigin || $origin === 'http://localhost:3000') {
        $allowedOrigin = $origin;
    } else {
        $allowedOrigin = '*';
    }

    return $response
        ->withHeader('Access-Control-Allow-Origin', $allowedOrigin)
        ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization')
        ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, PATCH, OPTIONS');
});

// ⚙️ Préflight OPTIONS
$app->options('/{routes:.+}', function (Request $request, Response $response) {
    return $response->withStatus(200);
});

// ===============================
// 🔹 Middleware Slim 4 requis
// ===============================
$app->addRoutingMiddleware();
$app->addBodyParsingMiddleware(); // ✅ ajouté ici

// ===============================
// 🔹 3. Inclure les routes
// ===============================
$routesFile = __DIR__ . '/../src/routes.php'; // ✅ chemin corrigé
if (!file_exists($routesFile)) {
    die("ERREUR: Le fichier de routes est introuvable à l'emplacement: " . $routesFile);
}
require $routesFile;

// 🚀 Lancer l’application
$app->run();
