<?php

namespace Src\Repositories;

use Src\Handlers\DatabaseHandler;
use PDO;
use Exception;
use PDOException;

/**
 * Gère toutes les opérations CRUD spécifiques aux données du quiz et des participants.
 */
class QuizRepository
{
    private PDO $db;

    public function __construct(DatabaseHandler $dbHandler)
    {
        // Récupère la connexion PDO unique
        $this->db = $dbHandler->getConnection();
    }

    /**
     * Récupère le participant par son pseudo pour vérification.
     * @param string $pseudo Le pseudo du participant.
     * @return array|false Les données du participant ou false s'il n'est pas trouvé.
     */
    public function getParticipantByPseudo(string $pseudo): array|false
    {
        $stmt = $this->db->prepare("SELECT * FROM participants WHERE pseudo = :pseudo");
        $stmt->execute(['pseudo' => $pseudo]);
        return $stmt->fetch();
    }

    /**
     * Enregistre un nouveau participant dans la base de données.
     * @param string $name Le nom complet.
     * @param string $pseudo Le pseudo unique.
     * @param string $email L'email du participant.
     * @param string $password Le mot de passe (haché).
     * @return int L'ID du participant inséré.
     * @throws Exception Si l'insertion échoue.
     */
    public function createParticipant(string $name, string $pseudo, string $email, string $password): int
    {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO participants (name, pseudo, email, password, score, is_admin, is_ready, hache)
                VALUES (:name, :pseudo, :email, :password_hash, 0, FALSE, FALSE, FALSE)
            ");
            $stmt->execute([
                'name' => $name,
                'pseudo' => $pseudo,
                'email' => $email,
                'password_hash' => password_hash($password, PASSWORD_BCRYPT),
            ]);

            // Récupère l'ID du dernier enregistrement inséré
            return $this->db->lastInsertId('participants_id_seq'); // Adaptez la séquence si nécessaire
        } catch (PDOException $e) {
            error_log("Error creating participant: " . $e->getMessage());
            throw new Exception("L'enregistrement du participant a échoué.");
        }
    }

    /**
     * Récupère la prochaine question pour un participant spécifique, qui n'a pas encore répondu à cette question.
     * @param int $participantId L'ID du participant.
     * @return array|false La question (ID, texte, options) ou false s'il n'y a plus de question.
     *
     * NOTE IMPORTANTE: Cette requête suppose l'existence de deux tables :
     * 1. `questions` (avec id, text, options, correct_answer_index)
     * 2. `participants_reponses` (avec participant_id, question_id, et d'autres colonnes)
     */
    public function getNextQuestion(int $participantId): array|false
    {
        try {
            // Requête SQL pour trouver une question que le participant n'a pas encore dans participants_reponses
            $sql = "
                SELECT q.id, q.text, q.options, q.correct_answer_index
                FROM questions q
                LEFT JOIN participants_reponses pr ON q.id = pr.question_id AND pr.participant_id = :participant_id
                WHERE pr.question_id IS NULL -- S'assure que la ligne n'existe pas pour ce participant
                ORDER BY RANDOM()
                LIMIT 1
            ";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['participant_id' => $participantId]);
            
            $question = $stmt->fetch();

            if (!$question) {
                return false; // Plus de questions disponibles
            }
            
            // Les données stockées en BDD sont des chaînes (string), nous devons les convertir si nécessaire
            // Le champ 'options' est généralement stocké en JSON ou en TEXT/ARRAY PostgreSQL
            if (is_string($question['options'])) {
                // Tentative de décoder si c'est du JSON (cas le plus courant en API)
                $question['options'] = json_decode($question['options'], true) ?? $question['options'];
            }

            return $question;

        } catch (PDOException $e) {
            error_log("Error retrieving next question: " . $e->getMessage());
            // En cas d'erreur de BDD, on renvoie false pour gérer l'erreur au niveau supérieur
            return false;
        }
    }
    
    // Ajoutez d'autres méthodes (getScore, updateScore, submitAnswer, etc.) ici...
}
