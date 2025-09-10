<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/security.php';


function app_config() {
    static $config = null;
    if ($config === null) {
        $config = require __DIR__ . '/../config.php';
    }
    return $config['app'];
}

function start_session() {
    $cfg = app_config();
    if (session_status() === PHP_SESSION_NONE) {
        session_name($cfg['session_name']);
        date_default_timezone_set($cfg['timezone'] ?? 'UTC');
        session_start();
    }
}

function auth_login(string $email, string $password): bool {
    $pdo = get_db();
    $stmt = $pdo->prepare('SELECT u.id, u.name, u.email, u.password_hash, r.name AS role FROM users u JOIN roles r ON r.id=u.role_id WHERE email=? AND is_active=1 LIMIT 1');
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    if (!$user || !password_verify($password, $user['password_hash'])) {
        record_failed_login($email);
        return false;
    }

    clear_failed_login_attempts($email);
    
    $_SESSION['user'] = [
        'id' => (int)$user['id'],
        'name' => $user['name'],
        'email' => $user['email'],
        'role' => $user['role'],
    ];
    return true;
}

function auth_logout() {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time()-42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}

function current_user() {
    return $_SESSION['user'] ?? null;
}

function require_login() {
    if (!current_user()) {
        if (function_exists('redirect_to_page')) { redirect_to_page('login'); } else { header('Location: ?page=login'); }
        exit;
    }
}

function require_role(array $roles) {
    $user = current_user();
    if (!$user || !in_array($user['role'], $roles, true)) {
        http_response_code(403);
        echo '<h3>Forbidden</h3><p>You do not have access to this page.</p>';
        exit;
    }
}

function require_page_permission(string $page) {
    $user = current_user();
    if (!$user) { if (function_exists('redirect_to_page')) { redirect_to_page('login'); } else { header('Location: ?page=login'); } exit; }
    $role = $user['role'];
    // Access map
    $access = [
        'Admin' => 'all',
        'Manager' => [
            'dashboard','categories','units','locations','suppliers','customers','products',
            'sales','purchases','expenses','invoice',
            'report_sales','report_purchases','report_profit','report_low_stock','report_payments'
        ],
        'Staff' => [
            'dashboard','sales','invoice'
        ]
    ];
    $allowed = $access[$role] ?? [];
    if ($allowed !== 'all' && !in_array($page, $allowed, true)) {
        http_response_code(403);
        echo '<h3>Forbidden</h3><p>Your role cannot access this page.</p>';
        exit;
    }
}

function is_setup_required(): bool {
    try {
        $pdo = get_db();
        $stmt = $pdo->query("SELECT password_hash FROM users WHERE email='admin@example.com' LIMIT 1");
        $row = $stmt->fetch();
        if (!$row) return false; // admin row missing means schema not seeded; treat as no setup required here
        return strpos($row['password_hash'], 'UseAppToSetRealPassword') !== false;
    } catch (Throwable $e) {
        return false;
    }
}