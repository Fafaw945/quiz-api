<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;
use DI\Container;

require __DIR__ . '/../vendor/autoload.php';

// ===============================
// 🔧 Afficher toutes les erreurs pour le débogage
// ===============================
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// ===============================
// 🔧 Charger les variables d'environnement
// ===============================
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->safeLoad();

// ===============================
// 🔧 Créer le container Slim et injecter PDO
// ===============================
$container = new Container();

$container->set('db', function () {
    $host = getenv('DB_HOST') ?: 'db';
    $port = getenv('DB_PORT') ?: 3306;
    $dbname = getenv('DB_DATABASE') ?: 'quiz_game';
    $user = getenv('DB_USERNAME') ?: 'root';
    $pass = getenv('DB_PASSWORD') ?: 'root';


    try {
        $dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";
        $pdo = new PDO($dsn, $user, $pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        error_log("Connexion MySQL réussie.");
        return $pdo;
    } catch (PDOException $e) {
        error_log("Erreur connexion MySQL : " . $e->getMessage());
        die("Erreur de connexion à la base de données : " . $e->getMessage());
    }
});

AppFactory::setContainer($container);
$app = AppFactory::create();

// ===============================
// 🌍 Middleware CORS
// ===============================
$app->add(function (Request $request, $handler) {
    $response = $handler->handle($request);
    $origin = $request->getHeaderLine('Origin') ?: '*';
    return $response
        ->withHeader('Access-Control-Allow-Origin', $origin)
        ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization')
        ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, PATCH, OPTIONS')
        ->withHeader('Access-Control-Allow-Credentials', 'true');
});

// Préflight OPTIONS
$app->options('/{routes:.+}', function (Request $request, Response $response) {
    return $response->withStatus(200);
});

// Middleware Slim requis
$app->addRoutingMiddleware();
$app->addBodyParsingMiddleware();

require __DIR__ . '/../src/routes.php';

// ===============================
// 🚀 Lancer l’application
// ===============================
$app->run();
