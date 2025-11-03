<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;
use DI\Container;

require __DIR__ . '/../vendor/autoload.php';

// ===============================
// 🔧 Afficher toutes les erreurs pour le débogage
// ===============================
// (Nous laissons ceci, car APP_DEBUG=false sur Render le désactivera)
ini_set('display_errors', 0);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// ===============================
// 🔧 Charger les variables d'environnement
// ===============================
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->safeLoad(); // safeLoad() n'échouera pas si .env est absent (parfait pour Render)

// ===============================
// 🔧 Créer le container Slim et injecter PDO
// ===============================
$container = new Container();

// === C'EST LE BLOC CORRIGÉ POUR RENDER (POSTGRESQL) ===
$container->set('db', function () {
    // Ces variables sont lues depuis l'environnement Render
    $host = getenv('DB_HOST');
    $port = getenv('DB_PORT');
    $dbname = getenv('DB_DATABASE');
    $user = getenv('DB_USERNAME');
    $pass = getenv('DB_PASSWORD');

    try {
        // 1. On utilise "pgsql:" au lieu de "mysql:"
        // 2. On supprime ";charset=utf8mb4" (non valide pour le DSN pgsql)
        $dsn = "pgsql:host=$host;port=$port;dbname=$dbname";
        
        $pdo = new PDO($dsn, $user, $pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        
        // Message de log corrigé pour la production
        error_log("Connexion PostgreSQL réussie."); 
        return $pdo;

    } catch (PDOException $e) {
        // Message de log corrigé pour la production
        error_log("Erreur connexion PostgreSQL : " . $e->getMessage());
        die("Erreur de connexion à la base de données : " . $e->getMessage());
    }
});
// === FIN DU BLOC CORRIGÉ ===

AppFactory::setContainer($container);
$app = AppFactory::create();

// ===============================
// 🌍 Middleware CORS
// ===============================
$app->add(function (Request $request, $handler) {
    $response = $handler->handle($request);
    // Autoriser toutes les origines pour le moment
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