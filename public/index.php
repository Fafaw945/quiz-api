<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;

require __DIR__ . '/vendor/autoload.php';

$app = AppFactory::create();

// ===============================
// 🔧 1️⃣ Connexion BDD PostgreSQL (Heroku)
// ===============================
$dbUrl = getenv('DATABASE_URL');
if (!$dbUrl) {
    // Fallback pour dev local PostgreSQL
    $dbUrl = "postgres://postgres:@localhost:5432/quiz_game";
}

$dbParams = parse_url($dbUrl);

// Construire le DSN PostgreSQL
$dsn = sprintf(
    'pgsql:host=%s;port=%s;dbname=%s',
    $dbParams['host'],
    $dbParams['port'] ?? 5432,
    ltrim($dbParams['path'], '/')
);

$pdo = new PDO(
    $dsn,
    $dbParams['user'] ?? null,
    $dbParams['pass'] ?? null
);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// ===============================
// 🌍 2️⃣ Middleware CORS
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

// Ajouter le Routing Middleware AVANT de lancer l'application
$app->addRoutingMiddleware();

// ===============================
// 🔹 3️⃣ Inclure les routes
// ===============================
$routesFile = __DIR__ . '/src/routes.php';
if (!file_exists($routesFile)) {
    die("ERREUR: Le fichier de routes est introuvable à l'emplacement: " . $routesFile);
}
require $routesFile;

// 🚀 Lancer l’application
$app->run();
