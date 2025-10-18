<?php 
use Psr\Http\Message\ResponseInterface as Response; 
use Psr\Http\Message\ServerRequestInterface as Request; 
use Slim\Factory\AppFactory; 

require __DIR__ . '/../vendor/autoload.php'; 

$app = AppFactory::create(); 

// 🔧 Connexion BDD 
$pdo = new PDO("mysql:host=localhost;dbname=quiz_game;charset=utf8", "root", ""); 
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); 

// =============================== 
// 🌍 Middleware CORS 
// =============================== 
$app->add(function (Request $request, $handler) { 
    $response = $handler->handle($request); 
    return $response 
        ->withHeader('Access-Control-Allow-Origin', 'http://localhost:3000') 
        ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization') 
        ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, PATCH, OPTIONS'); 
}); 

// ⚙️ Préflight OPTIONS 
$app->options('/{routes:.+}', function (Request $request, Response $response) { 
    return $response->withStatus(200); 
}); 

// Ajoutez le Routing Middleware AVANT de lancer l'application pour garantir que toutes les routes sont enregistrées.
$app->addRoutingMiddleware();

// =============================== 
// 🔹 Inclure les routes (avec vérification)
// =============================== 
$routesFile = __DIR__ . '/../src/routes.php';
if (!file_exists($routesFile)) {
    die("ERREUR: Le fichier de routes est introuvable à l'emplacement: " . $routesFile);
}
require $routesFile;

// 🚀 Lancer l’application 
$app->run();
