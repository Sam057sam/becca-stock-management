<?php
require_once __DIR__ . '/db.php';

const MAX_LOGIN_ATTEMPTS = 5;
const LOCKOUT_TIME_MINUTES = 15;

function is_account_locked(string $email): bool {
    $pdo = get_db();
    $stmt = $pdo->prepare('SELECT lockout_until FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $lockout_until = $stmt->fetchColumn();

    if ($lockout_until) {
        $now = new DateTime();
        $lockout_time = new DateTime($lockout_until);
        if ($now < $lockout_time) {
            return true;
        }
    }
    return false;
}

function record_failed_login(string $email) {
    $pdo = get_db();
    
    // First, get the current number of failed attempts
    $stmt_select = $pdo->prepare('SELECT failed_login_attempts FROM users WHERE email = ?');
    $stmt_select->execute([$email]);
    $attempts = $stmt_select->fetchColumn();

    if ($attempts === false) {
        // User does not exist, no need to do anything
        return;
    }

    $attempts++;

    if ($attempts >= MAX_LOGIN_ATTEMPTS) {
        // Lock the account
        $lockout_until = (new DateTime())->modify('+' . LOCKOUT_TIME_MINUTES . ' minutes')->format('Y-m-d H:i:s');
        $stmt_update = $pdo->prepare('UPDATE users SET failed_login_attempts = ?, lockout_until = ? WHERE email = ?');
        $stmt_update->execute([$attempts, $lockout_until, $email]);
    } else {
        // Just increment the failed attempts
        $stmt_update = $pdo->prepare('UPDATE users SET failed_login_attempts = ? WHERE email = ?');
        $stmt_update->execute([$attempts, $email]);
    }
}

function clear_failed_login_attempts(string $email) {
    $pdo = get_db();
    $stmt = $pdo->prepare('UPDATE users SET failed_login_attempts = 0, lockout_until = NULL WHERE email = ?');
    $stmt->execute([$email]);
}