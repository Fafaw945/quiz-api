<?php 
use Psr\Http\Message\ResponseInterface as Response; 
use Psr\Http\Message\ServerRequestInterface as Request; 
use Slim\Factory\AppFactory; 
use Slim\Middleware\BodyParsingMiddleware; // IMPORTANT: Ajout pour parser le JSON POST

require __DIR__ . '/../vendor/autoload.php'; // Chemin corrigé (le public/index.php est un niveau plus bas)

$app = AppFactory::create(); 

// =============================== 
// 🔧 1. Connexion BDD (Adaptée à Heroku PostgreSQL)
// =============================== 

// Tenter de lire l'URL de connexion de la base de données fournie par Heroku ou Vercel
$dbUrl = getenv('DATABASE_URL');

if (!$dbUrl) {
    // Fallback pour le développement local
    // N'oubliez pas de remplacer 'user' et 'password' par vos vrais identifiants locaux
    $dbUrl = "postgres://user:password@localhost:5432/quiz_game";
}

// Analyser l'URL de la BDD pour obtenir les paramètres
$dbParams = parse_url($dbUrl);

if (!$dbParams) {
    die("Erreur: Impossible d'analyser l'URL de la base de données.");
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
    $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC); // Ajout pour un meilleur fetch
    
} catch (PDOException $e) {
    error_log("Erreur de connexion à la BDD: " . $e->getMessage());
    // Affichage d'une erreur générique pour ne pas exposer les infos sensibles
    die("Erreur de connexion à la base de données.");
}


// =============================== 
// 🌍 2. Middleware CORS (Adapté à Vercel)
// =============================== 

// CORS doit être le premier middleware à être exécuté (après les gestionnaires de routes)
$app->add(function (Request $request, $handler) { 
    $response = $handler->handle($request); 
    
    // La liste des origines autorisées
    $allowedOrigins = [
        'https://quiz-app-eight-gold-57.vercel.app', 
        'https://quiz-api-fafaw945-13ff0b479a67.herokuapp.com',
        'http://localhost:3000'
    ]; 

    $origin = $request->getHeaderLine('Origin');

    // Vérifie si l'origine est dans la liste. Si non, utilise une origine générique ou la première.
    // L'utilisation de '*' est généralement acceptable si l'API est purement publique/sans jetons d'authentification stricts.
    if (in_array($origin, $allowedOrigins)) {
        $allowedOrigin = $origin;
    } else {
        $allowedOrigin = 'http://localhost:3000'; // Par défaut, autorise au moins le dev local
    }
    
    return $response 
        ->withHeader('Access-Control-Allow-Origin', $allowedOrigin) 
        ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization') 
        ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, PATCH, OPTIONS')
        ->withHeader('Access-Control-Allow-Credentials', 'true'); // Utile si vous utilisez des cookies/sessions
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
// 🔹 3. Inclure et Appeler les routes (CORRIGÉ)
// =============================== 
$routesFile = __DIR__ . '/../src/routes.php';
if (!file_exists($routesFile)) {
    die("ERREUR: Le fichier de routes est introuvable à l'emplacement: " . $routesFile);
}
// Le fichier routes.php retourne une fonction, nous devons l'appeler.
// Nous passons $db à l'intérieur de l'application via le conteneur pour que les routes y accèdent.
// NOTE: Comme vous n'utilisez pas le DI de Slim, nous devons adapter l'appel des routes.
// Pour que cela fonctionne avec le fichier routes.php que j'ai fourni précédemment,
// vous devez faire passer le $db à l'intérieur.

// Solution 1: Simuler le conteneur de dépendances pour passer $db (plus propre)

// Création d'un container simple pour y mettre la connexion $db
$container = new \Slim\Psr7\Factory\ResponseFactory(); // Utiliser un objet simple qui peut être remplacé

// Utilisation d'un hack simple pour rendre $db accessible si vous n'utilisez pas le conteneur de dépendances
// Si on veut rester fidèle à la fonction routes.php que j'ai fournie, il faut l'appeler.

$app->getContainer()->set('db', $db); // Ajouter $db au conteneur de Slim
$app->getContainer()->set('sendJsonResponse', function() use ($app) {
    // Fonction utilitaire pour envoyer des réponses JSON (réplique celle de routes.php)
    return function (Response $response, array $data, int $status = 200): Response {
        $response = $response->withHeader('Content-Type', 'application/json')->withStatus($status);
        $response->getBody()->write(json_encode($data));
        return $response;
    };
});

// Inclure et exécuter la fonction de routes
$routes = require $routesFile;
$routes($app);


// 🚀 Lancer l’application 
$app->run();
