<?php 
use Psr\Http\Message\ResponseInterface as Response; 
use Psr\Http\Message\ServerRequestInterface as Request; 
use Slim\Factory\AppFactory; 
use Slim\Middleware\BodyParsingMiddleware; 

require __DIR__ . '/../vendor/autoload.php'; 

$app = AppFactory::create(); 

// =============================== 
// 🔧 1. Connexion BDD (Adaptée à Heroku PostgreSQL)
// =============================== 

// Tenter de lire l'URL de connexion de la base de données fournie par Heroku ou Vercel
$dbUrl = getenv('DATABASE_URL');

if (!$dbUrl) {
    // Fallback pour le développement local
    // Assurez-vous que cette URL est correcte localement
    $dbUrl = "postgres://user:password@localhost:5432/quiz_game";
}

// Analyser l'URL de la BDD pour obtenir les paramètres
$dbParams = parse_url($dbUrl);

if (!$dbParams || !isset($dbParams['host'])) {
    // Si l'URL n'est pas parsée correctement ou manque l'hôte, on meurt.
    die("Erreur: Impossible d'analyser l'URL de la base de données ou l'hôte est manquant.");
}

// Construction du DSN pour PostgreSQL
$dsn = sprintf(
    'pgsql:host=%s;port=%d;dbname=%s;user=%s;password=%s',
    $dbParams['host'],
    $dbParams['port'] ?? 5432, 
    ltrim($dbParams['path'], '/'),
    $dbParams['user'],
    $dbParams['pass']
);

try {
    // Utilisation de $db pour l'instance PDO
    $db = new PDO($dsn); 
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); 
    $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC); 
    
} catch (PDOException $e) {
    error_log("Erreur de connexion à la BDD: " . $e->getMessage());
    // Affichage d'une erreur générique pour ne pas exposer les infos sensibles
    die("Erreur de connexion à la base de données. Détails dans les logs.");
}


// =============================== 
// 🌍 2. Middleware CORS (Adapté à Vercel)
// =============================== 

// CORS doit être le premier middleware à être exécuté (après les gestionnaires de routes)
$app->add(function (Request $request, $handler) { 
    $response = $handler->handle($request); 
    
    // Définir les origines autorisées (ajoutez votre domaine Vercel ici)
    $allowedOrigins = [
        'https://quiz-app-eight-gold-57.vercel.app', 
        'https://quiz-api-fafaw945-13ff0b479a67.herokuapp.com',
        'http://localhost:3000'
    ]; 

    $origin = $request->getHeaderLine('Origin');

    // Vérification de l'origine
    if (in_array($origin, $allowedOrigins)) {
        $allowedOrigin = $origin;
    } else {
        $allowedOrigin = '*'; // Utilisation d'un joker pour le moment, mais préférable de le restreindre
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

// Middleware pour parser les corps de requêtes JSON (très important)
$app->add(new BodyParsingMiddleware());

// Middleware de routage (doit être ajouté pour que les routes fonctionnent)
$app->addRoutingMiddleware();

// Gestion des erreurs
$errorMiddleware = $app->addErrorMiddleware(true, true, true);


// =============================== 
// 🔹 3. Inclure et Appeler les routes (CORRECTION getContainer)
// =============================== 
$routesFile = __DIR__ . '/../src/routes.php';
if (!file_exists($routesFile)) {
    die("ERREUR: Le fichier de routes est introuvable à l'emplacement: " . $routesFile);
}

// CORRECTION: On passe $db directement. Pour que cela fonctionne, on stocke $db dans le conteneur 
// via une méthode supportée par Slim 4 si un conteneur est fourni, ou on le passe directement.
// Ici, on simule l'injection de dépendance car votre fichier routes.php utilise getContainer().
// On utilise une petite astuce pour rendre $db accessible via le conteneur.
$container = $app->getContainer();
if ($container) {
    $container->set('db', $db);
}


// Inclure et exécuer la fonction de routes
$routes = require $routesFile;
// Le fichier src/routes.php DOIT retourner une fonction de la forme :
// return function (Slim\App $app) { ... };
$routes($app);


// 🚀 Lancer l’application 
$app->run();
