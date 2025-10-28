<?php

namespace Src\Middleware;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Src\Repositories\QuizRepository;
use Exception;

/**
 * AuthMiddleware vérifie l'existence et la validité d'un participant
 * à partir d'un en-tête HTTP (ex: X-Participant-ID).
 * * Il implémente l'interface MiddlewareInterface pour Slim 4.
 */
class AuthMiddleware implements MiddlewareInterface
{
    private QuizRepository $quizRepository;
    private $setJsonResponse;
    
    // Pour une API simple, on lit l'ID directement, mais une approche 
    // par jeton (JWT) serait plus robuste en production.
    private const PARTICIPANT_HEADER = 'X-Participant-ID'; 

    /**
     * @param QuizRepository $quizRepository Le repository pour vérifier l'utilisateur.
     * @param callable $setJsonResponse La fonction utilitaire pour formater la réponse JSON.
     */
    public function __construct(QuizRepository $quizRepository, callable $setJsonResponse)
    {
        $this->quizRepository = $quizRepository;
        $this->setJsonResponse = $setJsonResponse;
    }

    /**
     * Traite une requête et retourne une réponse.
     * @param Request $request La requête en entrée.
     * @param RequestHandler $handler Le gestionnaire de requête suivant.
     * @return Response La réponse traitée.
     */
    public function process(Request $request, RequestHandler $handler): Response
    {
        // 1. Tenter de récupérer l'ID depuis l'en-tête
        // Note: getHeaderLine est la méthode standard dans PSR-7 (Slim)
        $participantIdHeader = $request->getHeaderLine(self::PARTICIPANT_HEADER);

        // Si l'ID n'est pas trouvé dans l'en-tête
        if (empty($participantIdHeader)) {
            return $this->denyAccess($request, 'Authentification requise. En-tête ' . self::PARTICIPANT_HEADER . ' manquant.', 401);
        }

        $participantId = (int) $participantIdHeader;

        try {
            // 2. Vérifier l'existence et la validité de l'ID via le Repository
            $participant = $this->quizRepository->getParticipantById($participantId);

            if (!$participant) {
                return $this->denyAccess($request, 'Participant ID invalide ou inexistant.', 401);
            }

            // 3. Authentification réussie : Ajouter l'ID du participant à l'attribut de la requête
            // pour qu'elle soit accessible dans le contrôleur (route).
            $request = $request->withAttribute('participant_id', $participantId);

            // 4. Passer au gestionnaire de requête suivant (la route elle-même)
            return $handler->handle($request);

        } catch (Exception $e) {
            // Erreur de base de données ou autre
            error_log("AuthMiddleware DB Error: " . $e->getMessage());
            return $this->denyAccess($request, 'Erreur interne du serveur lors de la vérification de l\'authentification.', 500);
        }
    }

    /**
     * Envoie une réponse d'erreur 401 ou 403.
     *
     * @param Request $request La requête pour obtenir l'objet de réponse initial.
     * @param string $message Le message d'erreur à envoyer au client.
     * @param int $status Le code de statut HTTP (401 par défaut).
     * @return Response
     */
    private function denyAccess(Request $request, string $message, int $status = 401): Response
    {
        // Utilise la fonction utilitaire passée au constructeur
        $response = (new \Slim\Psr7\Response())->withStatus($status);
        
        $setJsonResponse = $this->setJsonResponse;
        return $setJsonResponse($response, ['error' => $message], $status);
    }
}
