<?php
class Player {
    private $conn;
    private $table = "players";

    public $id;
    public $name;
    public $score;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function create() {
        $query = "INSERT INTO " . $this->table . " (name, score) VALUES (:name, :score)";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":name", $this->name);
        $stmt->bindParam(":score", $this->score);
        return $stmt->execute();
    }

    public function readAll() {
        $query = "SELECT * FROM " . $this->table . " ORDER BY score DESC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt;
    }
}
