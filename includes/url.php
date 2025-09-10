<?php

function base_path_prefix(): string {
    $script = $_SERVER['SCRIPT_NAME'] ?? '';
    $dir = rtrim(str_replace('\\','/', dirname($script)), '/');
    // Typically '/.../public' — trim the '/public' to get app root path prefix
    $base = preg_replace('#/public$#', '', $dir);
    if ($base === '' || $base === '/') return '/';
    return $base . '/';
}

function base_url(string $path = ''): string {
    $prefix = base_path_prefix();
    return $prefix . ltrim($path, '/');
}

function page_url(string $page, array $params = []): string {
    $url = base_url($page);
    if (!empty($params)) {
        $url .= '?' . http_build_query($params);
    }
    return $url;
}

function redirect_to_page(string $page, array $params = []): void {
    $url = page_url($page, $params);
    header('Location: ' . $url);
    exit;
}

