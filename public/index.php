<?php 
use Psr\Http\Message\ResponseInterface as Response; 
use Psr\Http\Message\ServerRequestInterface as Request; 
use Slim\Factory\AppFactory; 

require __DIR__ . '/vendor/autoload.php'; // Correction du chemin pour Heroku

$app = AppFactory::create(); 

// =============================== 
// 🔧 1. Connexion BDD (Adaptée à Heroku)
// =============================== 

// Tenter de lire l'URL de connexion de la base de données fournie par Heroku
$dbUrl = getenv('DATABASE_URL');
if (!$dbUrl) {
    // Fallback pour le développement local si DATABASE_URL n'est pas définie (non recommandé pour la prod)
    $dbUrl = "mysql://root:@localhost:3306/quiz_game";
}

// Analyser l'URL de la BDD pour obtenir les paramètres
$dbParams = parse_url($dbUrl);

// Assurez-vous que les paramètres sont corrects pour ClearDB/JawsDB
$dsn = sprintf(
    'mysql:host=%s;dbname=%s;charset=utf8',
    $dbParams['host'],
    ltrim($dbParams['path'], '/')
);

$pdo = new PDO(
    $dsn,
    $dbParams['user'],
    $dbParams['pass']
); 
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); 

// =============================== 
// 🌍 2. Middleware CORS (Adapté à Vercel)
// =============================== 
$app->add(function (Request $request, $handler) { 
    $response = $handler->handle($request); 
    
    // URL de votre frontend Vercel (quiz-app-eight-gold-57.vercel.app)
    $vercelOrigin = 'https://quiz-app-eight-gold-57.vercel.app'; 
    $herokuOrigin = 'https://quiz-api-fafaw945-13ff0b479a67.herokuapp.com'; // Ajouté par précaution

    // On autorise la bonne origine si elle est présente dans les requêtes
    $origin = $request->getHeaderLine('Origin');

    if ($origin === $vercelOrigin || $origin === $herokuOrigin || $origin === 'http://localhost:3000') {
        $allowedOrigin = $origin;
    } else {
        // Fallback générique pour les requêtes qui n'auraient pas d'Origin (moins sécurisé mais fonctionnel)
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

// Ajoutez le Routing Middleware AVANT de lancer l'application pour garantir que toutes les routes sont enregistrées.
$app->addRoutingMiddleware();

// =============================== 
// 🔹 3. Inclure les routes
// =============================== 
$routesFile = __DIR__ . '/src/routes.php'; // Correction du chemin
if (!file_exists($routesFile)) {
    die("ERREUR: Le fichier de routes est introuvable à l'emplacement: " . $routesFile);
}
require $routesFile;

// 🚀 Lancer l’application 
$app->run();
