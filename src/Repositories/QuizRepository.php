<?php

namespace Src\Repositories;

use Src\Handlers\DatabaseHandler;
use PDO;
use Exception;

/**
 * QuizRepository gère toutes les interactions avec la base de données
 * pour les participants, les questions, les réponses et les scores.
 */
class QuizRepository
{
    private DatabaseHandler $dbHandler;

    public function __construct(DatabaseHandler $dbHandler)
    {
        $this->dbHandler = $dbHandler;
    }

    // --- Participants (Création et Récupération) ---

    /**
     * Crée un nouveau participant dans la base de données.
     * @param string $name Nom réel du participant.
     * @param string $pseudo Pseudo du participant.
     * @param string $email Email du participant.
     * @param string $password Mot de passe non haché.
     * @return int L'ID du participant créé.
     * @throws Exception Si l'insertion échoue.
     */
    public function createParticipant(string $name, string $pseudo, string $email, string $password): int
    {
        $db = $this->dbHandler->getConnection();
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        $score = 0; // Le score initial est de 0

        $sql = "INSERT INTO participants (name, pseudo, email, password_hash, score) VALUES (:name, :pseudo, :email, :password_hash, :score)";

        $stmt = $db->prepare($sql);
        $stmt->bindParam(':name', $name);
        $stmt->bindParam(':pseudo', $pseudo);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':password_hash', $passwordHash);
        $stmt->bindParam(':score', $score, PDO::PARAM_INT);

        if (!$stmt->execute()) {
            throw new Exception("Échec de l'insertion du participant.");
        }

        // Récupère l'ID inséré (spécifique à PostgreSQL ou d'autres DB si la séquence est spécifiée)
        return (int)$db->lastInsertId('participants_id_seq');
    }

    /**
     * Récupère un participant par son pseudo.
     * @param string $pseudo Le pseudo du participant.
     * @return array|false Le participant ou false s'il n'existe pas.
     */
    public function getParticipantByPseudo(string $pseudo): array|false
    {
        $db = $this->dbHandler->getConnection();
        $sql = "SELECT id, pseudo FROM participants WHERE pseudo = :pseudo";
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':pseudo', $pseudo);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère un participant par son ID. (Utilisé par l'AuthMiddleware)
     * @param int $id L'ID du participant.
     * @return array|false Le participant ou false s'il n'existe pas.
     */
    public function getParticipantById(int $id): array|false
    {
        $db = $this->dbHandler->getConnection();
        $sql = "SELECT id, pseudo, score FROM participants WHERE id = :id";
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère un participant par son email pour la connexion.
     * @param string $email L'email du participant.
     * @return array|false Le participant (avec hash) ou false s'il n'existe pas.
     */
    public function findParticipantByEmail(string $email): array|false
    {
        $db = $this->dbHandler->getConnection();
        $sql = "SELECT id, pseudo, password_hash, score FROM participants WHERE email = :email";
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':email', $email);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }


    // --- Questions et Réponses ---

    /**
     * Récupère la prochaine question à poser au participant.
     * Exclut les questions déjà répondues.
     * @param int $participantId L'ID du participant.
     * @return array|false La prochaine question ou false si toutes les questions sont répondues.
     */
    public function getNextQuestion(int $participantId): array|false
    {
        $db = $this->dbHandler->getConnection();

        // Sélectionne une question qui n'a pas été répondue par le participant
        $sql = "SELECT 
                    q.id, q.question_text, q.options, q.type
                FROM 
                    questions q
                LEFT JOIN 
                    answers a ON q.id = a.question_id AND a.participant_id = :participant_id
                WHERE 
                    a.question_id IS NULL
                ORDER BY 
                    q.id ASC 
                LIMIT 1";

        $stmt = $db->prepare($sql);
        $stmt->bindParam(':participant_id', $participantId, PDO::PARAM_INT);
        $stmt->execute();
        $question = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($question) {
            // Les options sont stockées en JSON (TEXT/VARCHAR) dans la BDD.
            if (!empty($question['options'])) {
                $question['options'] = json_decode($question['options'], true);
            }
            return $question;
        }

        return false;
    }

    /**
     * Soumet la réponse d'un participant à une question.
     * @param int $participantId L'ID du participant.
     * @param int $questionId L'ID de la question.
     * @param mixed $submittedAnswer La réponse soumise.
     * @return array Le résultat de la soumission (is_correct, correct_answer, score_earned).
     * @throws Exception Si la question n'existe pas ou la réponse est déjà soumise.
     */
    public function submitAnswer(int $participantId, int $questionId, mixed $submittedAnswer): array
    {
        $db = $this->dbHandler->getConnection();
        
        // 1. Vérifier si la question a déjà été répondue
        $checkSql = "SELECT COUNT(*) FROM answers WHERE participant_id = :pid AND question_id = :qid";
        $checkStmt = $db->prepare($checkSql);
        $checkStmt->bindParam(':pid', $participantId, PDO::PARAM_INT);
        $checkStmt->bindParam(':qid', $questionId, PDO::PARAM_INT);
        $checkStmt->execute();

        if ($checkStmt->fetchColumn() > 0) {
            throw new Exception("Question déjà répondue.");
        }

        // 2. Récupérer la bonne réponse et le score de la question
        $questionSql = "SELECT correct_answer, score FROM questions WHERE id = :qid";
        $questionStmt = $db->prepare($questionSql);
        $questionStmt->bindParam(':qid', $questionId, PDO::PARAM_INT);
        $questionStmt->execute();
        $questionData = $questionStmt->fetch(PDO::FETCH_ASSOC);

        if (!$questionData) {
            throw new Exception("Question non trouvée.");
        }

        $correctAnswer = $questionData['correct_answer'];
        $maxScore = (int)$questionData['score'];

        // 3. Déterminer si la réponse est correcte et quel score attribuer
        $isCorrect = (string)$submittedAnswer === (string)$correctAnswer;
        $scoreEarned = $isCorrect ? $maxScore : 0;
        
        // 4. Enregistrer la réponse
        $insertSql = "INSERT INTO answers (participant_id, question_id, submitted_answer, is_correct, score_earned) 
                      VALUES (:pid, :qid, :answer, :is_correct, :score_earned)";
        $insertStmt = $db->prepare($insertSql);
        
        // Assurer que la réponse soumise est convertie en string pour l'insertion
        $submittedAnswerStr = is_array($submittedAnswer) ? json_encode($submittedAnswer) : (string)$submittedAnswer;

        $insertStmt->bindParam(':pid', $participantId, PDO::PARAM_INT);
        $insertStmt->bindParam(':qid', $questionId, PDO::PARAM_INT);
        $insertStmt->bindParam(':answer', $submittedAnswerStr);
        $insertStmt->bindValue(':is_correct', $isCorrect, PDO::PARAM_BOOL);
        $insertStmt->bindParam(':score_earned', $scoreEarned, PDO::PARAM_INT);

        if (!$insertStmt->execute()) {
            throw new Exception("Échec de l'enregistrement de la réponse.");
        }

        // 5. Mettre à jour le score du participant
        if ($scoreEarned > 0) {
            $updateSql = "UPDATE participants SET score = score + :score_earned WHERE id = :pid";
            $updateStmt = $db->prepare($updateSql);
            $updateStmt->bindParam(':score_earned', $scoreEarned, PDO::PARAM_INT);
            $updateStmt->bindParam(':pid', $participantId, PDO::PARAM_INT);
            $updateStmt->execute();
        }

        return [
            'is_correct' => $isCorrect,
            'correct_answer' => $correctAnswer,
            'score_earned' => $scoreEarned
        ];
    }


    // --- Scores et Classement ---

    /**
     * Récupère le score actuel d'un participant.
     * @param int $participantId L'ID du participant.
     * @return int Le score total.
     */
    public function getParticipantScore(int $participantId): int
    {
        $db = $this->dbHandler->getConnection();
        $sql = "SELECT score FROM participants WHERE id = :pid";
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':pid', $participantId, PDO::PARAM_INT);
        $stmt->execute();
        $score = $stmt->fetchColumn();
        return (int)$score;
    }

    /**
     * Récupère le classement des participants.
     * @return array Le tableau de classement (pseudo et score).
     */
    public function getLeaderboard(): array
    {
        $db = $this->dbHandler->getConnection();
        $sql = "SELECT pseudo, score FROM participants ORDER BY score DESC, pseudo ASC LIMIT 10";
        $stmt = $db->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
