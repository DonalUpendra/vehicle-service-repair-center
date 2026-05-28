<?php
require_once __DIR__ . '/../config/init.php';

function requireAuth() {
    if (!isset($_SESSION['user'])) {
        jsonError('Unauthorized. Please login.', 401);
    }
}

function requireAdmin() {
    requireAuth();
    if ($_SESSION['user']['role'] !== 'admin') {
        jsonError('Forbidden. Admin access required.', 403);
    }
}

function requireTechnician() {
    requireAuth();
    $role = $_SESSION['user']['role'];
    if ($role !== 'technician' && $role !== 'admin') {
        jsonError('Forbidden. Technician access required.', 403);
    }
}

function getCurrentUserId() {
    return $_SESSION['user']['id'] ?? null;
}

function getCurrentUserRole() {
    return $_SESSION['user']['role'] ?? null;
}
