<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;

require __DIR__ . '/../vendor/autoload.php';

error_log("INDEX.PHP start");

// Créer l'application Slim
$app = AppFactory::create();

// ===============================
// 🔧 1. Connexion BDD (Heroku PGSQL + fallback MySQL local)
// ===============================

// Charger les variables d'environnement
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->safeLoad();

$pdo = null;

if (!empty($_ENV['DATABASE_URL'])) {
    // Heroku PostgreSQL
    $dbUrl = str_replace("postgres://", "pgsql://", $_ENV['DATABASE_URL']);
    $dbParams = parse_url($dbUrl);

    $dsn = sprintf(
        'pgsql:host=%s;port=%s;dbname=%s',
        $dbParams['host'],
        $dbParams['port'] ?? 5432,
        ltrim($dbParams['path'], '/')
    );

    $user = $dbParams['user'];
    $pass = $dbParams['pass'];

    try {
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_TIMEOUT => 5,
            PDO::PGSQL_ATTR_SSLMODE => 'require' // SSL obligatoire sur Heroku
        ]);
        error_log("Connexion BDD PostgreSQL réussie.");
    } catch (PDOException $e) {
        error_log("Erreur connexion PostgreSQL : " . $e->getMessage());
        http_response_code(500);
        die("Erreur de connexion à la base PostgreSQL. Vérifiez les logs.");
    }

} else {
    // Fallback local MySQL
    try {
        $pdo = new PDO('mysql:host=localhost;dbname=quiz_game;charset=utf8', 'root', '');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        error_log("Connexion BDD MySQL locale réussie.");
    } catch (PDOException $e) {
        error_log("Erreur connexion MySQL : " . $e->getMessage());
        http_response_code(500);
        die("Erreur de connexion à la base MySQL locale.");
    }
}

// Exposer $pdo globalement pour les routes
$GLOBALS['db'] = $pdo;

// ===============================
// 🌍 2. Middleware CORS
// ===============================
$app->add(function (Request $request, $handler) {
    $response = $handler->handle($request);

    $allowedOrigins = [
        'https://quiz-app-eight-gold-57.vercel.app',
        'http://localhost:3000'
    ];

    $origin = $request->getHeaderLine('Origin');
    $allowedOrigin = in_array($origin, $allowedOrigins) ? $origin : '*';

    return $response
        ->withHeader('Access-Control-Allow-Origin', $allowedOrigin)
        ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization')
        ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, PATCH, OPTIONS')
        ->withHeader('Access-Control-Allow-Credentials', 'true');
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
$routesFile = __DIR__ . '/../src/routes.php';
if (!file_exists($routesFile)) {
    die("ERREUR: Le fichier de routes est introuvable à l'emplacement: " . $routesFile);
}
require $routesFile;

// 🚀 Lancer l’application
$app->run();
