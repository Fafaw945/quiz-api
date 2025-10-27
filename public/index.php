<?php 
use Psr\Http\Message\ResponseInterface as Response; 
use Psr\Http\Message\ServerRequestInterface as Request; 
use Slim\Factory\AppFactory; 
use Slim\Middleware\BodyParsingMiddleware; 

require __DIR__ . '/../vendor/autoload.php'; 

$app = AppFactory::create(); 

// =============================== 
// 🔧 1. Connexion BDD (L'INITIALISATION PDO EST MAINTENANT DANS db.php)
// =============================== 

// Inclus le fichier db.php qui gère l'initialisation de $db via getDatabaseConnection()
// S'il y a une erreur de connexion, db.php arrête l'exécution et affiche un message 500.
// $db est maintenant disponible pour le conteneur.
require __DIR__ . '/../src/db.php'; 


// =============================== 
// 🌍 2. Middleware CORS 
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
        $allowedOrigin = '*'; // Utilisation d'un joker si l'origine n'est pas reconnue
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
// 🔹 3. Inclure et Appeler les routes (Injection de $db)
// =============================== 
$routesFile = __DIR__ . '/../src/routes.php';
if (!file_exists($routesFile)) {
    die("ERREUR: Le fichier de routes est introuvable à l'emplacement: " . $routesFile);
}

// Assurez-vous que $db (initialisé dans db.php) est injecté dans le conteneur Slim
$container = $app->getContainer();
if ($container) {
    // Si $db existe (initialisé dans db.php), on le place dans le conteneur
    // pour qu'il soit disponible via $this->get('db') ou $container->get('db')
    if (isset($db)) {
        $container->set('db', $db);
    } else {
        // Ceci ne devrait pas arriver si db.php fonctionne correctement
        error_log("FATAL: \$db n'a pas été initialisé par db.php.");
        die("Erreur de configuration interne: La connexion DB est introuvable.");
    }
}


// Inclure et exécuer la fonction de routes
$routes = require $routesFile;
// Le fichier src/routes.php DOIT retourner une fonction de la forme :
// return function (Slim\App $app) { ... };
$routes($app);


// 🚀 Lancer l’application 
$app->run();
