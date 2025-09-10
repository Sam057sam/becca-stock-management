<?php
require_once __DIR__ . '/db.php';

function settings_all(): array {
    if (!table_exists('settings')) return [];
    $pdo = get_db();
    $rows = $pdo->query('SELECT `key`,`value` FROM settings')->fetchAll();
    $out = [];
    foreach ($rows as $r) { $out[$r['key']] = $r['value']; }
    return $out;
}

function setting(string $key, $default = null) {
    static $cache = null;
    if ($cache === null) $cache = settings_all();
    return $cache[$key] ?? $default;
}

function settings_set(array $kv): void {
    if (!table_exists('settings')) return;
    $pdo = get_db();
    $stmt = $pdo->prepare('INSERT INTO settings(`key`,`value`) VALUES (?,?) ON DUPLICATE KEY UPDATE `value`=VALUES(`value`)');
    foreach ($kv as $k=>$v) { $stmt->execute([$k, $v]); }
}

