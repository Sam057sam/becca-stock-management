<?php
function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string {
    $t = htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8');
    return '<input type="hidden" name="_token" value="'.$t.'">';
}

function csrf_verify() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $sent = $_POST['_token'] ?? '';
        if (!hash_equals($_SESSION['csrf_token'] ?? '', $sent)) {
            http_response_code(400);
            echo 'Invalid CSRF token';
            exit;
        }
    }
}

function csrf_verify_get() {
    $sent = $_GET['_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $sent)) {
        http_response_code(400);
        echo 'Invalid CSRF token';
        exit;
    }
}
