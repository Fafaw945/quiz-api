<?php
// Test simple Heroku PHP

// Affiche les erreurs (uniquement pour debug, à enlever en prod)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Log pour Heroku
error_log("INDEX.PHP minimal start");

// Réponse simple pour tester
echo json_encode([
    'status' => 'ok',
    'message' => 'Heroku PHP fonctionne !'
]);

error_log("INDEX.PHP minimal end");
