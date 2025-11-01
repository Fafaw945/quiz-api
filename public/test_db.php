<?php
$host = getenv('DATABASE_HOST') ?: 'db';
$db   = getenv('DATABASE_NAME') ?: 'quiz';
$user = getenv('DATABASE_USER') ?: 'root';
$pass = getenv('DATABASE_PASSWORD') ?: 'root';

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$db;charset=utf8mb4",
        $user,
        $pass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );

    echo "<h2 style='color:green;'>Connexion MySQL Docker réussie ✅</h2>";

    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);

    if (empty($tables)) {
        echo "<p style='color:orange;'>Aucune table trouvée dans la base <b>$db</b>.</p>";
    } else {
        echo "<h3>📋 Tables dans $db :</h3>";
        echo "<ul>";
        foreach ($tables as $table) {
            echo "<li>$table</li>";
        }
        echo "</ul>";

        echo "<h3>🔢 Nombre de lignes par table :</h3>";
        echo "<ul>";
        foreach ($tables as $table) {
            $stmt = $pdo->query("SELECT COUNT(*) AS total FROM `$table`");
            $count = $stmt->fetch()['total'];
            echo "<li>$table : $count</li>";
        }
        echo "</ul>";
    }

} catch (PDOException $e) {
    echo "<p style='color:red;'>❌ Erreur connexion MySQL Docker : " . htmlspecialchars($e->getMessage()) . "</p>";
}
?>
