<?php
class Response {
    public static function json($data, $status = 200) {
        header("Content-Type: application/json; charset=UTF-8");
        http_response_code($status);
        echo json_encode($data);
        exit;
    }
}
