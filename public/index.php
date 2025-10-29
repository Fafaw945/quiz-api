<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;

require __DIR__ . '/../vendor/autoload.php';

error_log("INDEX.PHP start");

$app = AppFactory::create();

// ===============================
// 🔧 1. Connexion BDD (Adaptée à Heroku - PGSQL + SSL OBLIGATOIRE)
// ===============================

$dbUrl = getenv('DATABASE_URL');
$pdo = null;

if ($dbUrl) {
    // 1. Remplacer 'postgres://' par 'pgsql://' pour la compatibilité PDO
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

    // Options PDO pour forcer le SSL/TLS (Critique sur Heroku)
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        // Ajouter un timeout de 5s pour éviter le timeout H12 d'Heroku (30s) en cas d'échec
        PDO::ATTR_TIMEOUT => 5, 
        // Forcer la connexion sécurisée (SSL/TLS)
        PDO::PGSQL_ATTR_SSLMODE => 'require' 
    ];

    try {
        // On passe le DSN, et les options (le user/pass peut être null car il est déjà dans le DSN)
        $pdo = new PDO($dsn, null, null, $options); 
        
        // Exposer $pdo globalement pour les routes
        $GLOBALS['db'] = $pdo; 
        error_log("DB Connection successful with PGSQL/SSL.");

    } catch (PDOException $e) {
        error_log("DB CONNECTION ERROR: " . $e->getMessage());
        // En cas d'échec de connexion BDD, on arrête l'application.
        http_response_code(500);
        // Afficher un message plus précis pour le debug
        die("Erreur de connexion à la base de données PostgreSQL. Vérifiez vos logs Heroku pour le message : " . $e->getMessage());
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
        'http://localhost:3000' 
    ];
    
    $origin = $request->getHeaderLine('Origin');
    $allowedOrigin = '*'; 

    if (in_array($origin, $allowedOrigins)) {
        $allowedOrigin = $origin;
    }

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
$routesFile = __DIR__ . '/../routes.php';
if (!file_exists($routesFile)) {
    die("ERREUR: Le fichier de routes est introuvable à l'emplacement: " . $routesFile);
}
require $routesFile;

// 🚀 Lancer l’application
$app->run();
