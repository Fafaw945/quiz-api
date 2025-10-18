<?php
require_once "models/Player.php";
require_once "config/database.php";
require_once "utils/Response.php";

class PlayerController {
    private $db;

    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
    }

    public function getPlayers() {
        $player = new Player($this->db);
        $stmt = $player->readAll();
        $players = $stmt->fetchAll(PDO::FETCH_ASSOC);
        Response::json($players);
    }

    public function addPlayer() {
        $data = json_decode(file_get_contents("php://input"), true);
        $player = new Player($this->db);
        $player->name = $data["name"];
        $player->score = 0;
        $player->create();
        Response::json(["message" => "Joueur ajouté avec succès !"]);
    }
}
