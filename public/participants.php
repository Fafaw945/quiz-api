<?php
require __DIR__ . '/../vendor/autoload.php'; // chemin selon ton projet

// Charger les variables d'environnement
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

// Connexion à la base de données
try {
    $pdo = new PDO(
        "mysql:host=".$_ENV['DB_HOST'].";dbname=".$_ENV['DB_NAME'].";charset=utf8",
        $_ENV['DB_USER'],
        $_ENV['DB_PASS']
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
    die(json_encode(["error" => "Erreur DB : " . $e->getMessage()]));
}

// Lire les données JSON envoyées depuis le front
$data = json_decode(file_get_contents("php://input"), true);

if (empty($data['nom']) || empty($data['prenom']) || empty($data['email']) || empty($data['pseudo']) || empty($data['password'])) {
    echo json_encode(["error" => "Tous les champs sont requis"]);
    exit;
}

// Vérifier si email ou pseudo existe déjà
$stmt = $pdo->prepare("SELECT * FROM players WHERE email = ? OR pseudo = ?");
$stmt->execute([$data['email'], $data['pseudo']]);
if ($stmt->fetch()) {
    echo json_encode(["error" => "Email ou pseudo déjà utilisé"]);
    exit;
}

// Hasher le mot de passe
$hashedPassword = password_hash($data['password'], PASSWORD_DEFAULT);

// Ajouter le joueur dans la BDD
$stmt = $pdo->prepare("INSERT INTO players (nom, prenom, email, pseudo, password, score) VALUES (?, ?, ?, ?, ?, 0)");
$stmt->execute([$data['nom'], $data['prenom'], $data['email'], $data['pseudo'], $hashedPassword]);

echo json_encode(["success" => "Compte créé avec succès !"]);

