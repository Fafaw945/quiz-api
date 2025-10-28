<?php 
use Psr\Http\Message\ResponseInterface as Response; 
use Psr\Http\Message\ServerRequestInterface as Request; 
use Slim\Factory\AppFactory; 

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
    $dbUrl = getenv('DATABASE_URL');
    if (!$dbUrl) {
        throw new Exception("La variable d'environnement DATABASE_URL est manquante.");
    }

    $dbParams = parse_url($dbUrl);

    if (!isset($dbParams['scheme']) || $dbParams['scheme'] !== 'postgres') {
        throw new Exception("L'URL de la base de données n'utilise pas le schéma 'postgres'.");
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
    // Erreur critique de connexion : Afficher le message pour le débogage Heroku
    die("Erreur de connexion à la base de données: " . $e->getMessage()); 
}


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

$app->addRoutingMiddleware();

// =============================== 
// 🔹 3. Inclure les routes
// =============================== 
$routesFile = $app_root . '/src/routes.php'; 
if (!file_exists($routesFile)) {
    die("ERREUR: Le fichier de routes est introuvable à l'emplacement: " . $routesFile);
}
// Le fichier routes.php retourne une fonction, nous l'appelons ici
$routes = require $routesFile;
$routes($app);

// 🚀 Lancer l’application 
$app->run();
