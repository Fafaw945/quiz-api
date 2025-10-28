<?php

namespace Src\Middlewares;

/**
 * Middleware pour vérifier si un participant est identifié (via l'ID dans le header).
 */
class AuthMiddleware
{
    /**
     * Exécute la logique du middleware.
     * @return int|false L'ID du participant ou false si l'authentification échoue.
     */
    public function handle(): int|false
    {
        // Tente de récupérer l'ID du participant à partir d'un en-tête personnalisé
        // En PHP, les headers sont souvent préfixés par 'HTTP_'
        $participantId = $_SERVER['HTTP_X_PARTICIPANT_ID'] ?? null;

        if (!$participantId || !is_numeric($participantId) || $participantId <= 0) {
            // L'ID est manquant ou invalide
            http_response_code(401); // Non autorisé
            echo json_encode(['error' => 'Authentification requise. Header X-Participant-ID manquant ou invalide.']);
            return false;
        }

        // L'ID du participant est valide
        return (int)$participantId;
    }
}
