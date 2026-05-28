<?php
session_start();

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Credentials: true');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

function jsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function jsonError($message, $statusCode = 400) {
    jsonResponse(['error' => $message], $statusCode);
}

function getJsonInput() {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    return $data ?: [];
}

function generateToken() {
    return bin2hex(random_bytes(32));
}
