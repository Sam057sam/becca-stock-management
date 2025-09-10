<?php
$cfg = require __DIR__ . '/../../config.php';
$app = $cfg['app'];
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= htmlspecialchars($app['name']) ?></title>
  <link rel="preconnect" href="https://fonts.gstatic.com/" crossorigin>
  <link rel="stylesheet" as="style" href="https://fonts.googleapis.com/css2?display=swap&amp;family=Inter%3Awght%40400%3B500%3B700%3B900&amp;family=Noto+Sans%3Awght%40400%3B500%3B700%3B900" onload="this.rel='stylesheet'">
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined">
  <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
  <link rel="stylesheet" href="<?= base_url('assets/style.css') ?>">
  <style type="text/tailwindcss">
    :root { --primary-color:#1173d4; --secondary-color:#f0f6ff; --text-primary:#111827; --text-secondary:#6b7280; --background-primary:#ffffff; --background-secondary:#f3f4f6; }
    body { font-family: Inter, "Noto Sans", sans-serif; }
    .material-symbols-outlined { font-variation-settings:'FILL' 0,'wght' 400,'GRAD' 0,'opsz' 24 }
  </style>
</head>
<body class="bg-gray-50">
<div class="relative flex size-full min-h-screen flex-col overflow-x-hidden">
  <div class="flex h-full w-full">
    <aside class="flex flex-col w-64 bg-[var(--background-primary)] border-r border-gray-200">
      <div class="flex items-center justify-center h-16 border-b border-gray-200">
        <?php if (is_file(__DIR__.'/../../public/assets/logo.png')): ?>
          <img src="<?= base_url('assets/logo.png') ?>" alt="Logo" class="h-9 w-auto"/>
        <?php else: ?>
          <span class="material-symbols-outlined text-[var(--primary-color)] text-2xl">inventory_2</span>
        <?php endif; ?>
      </div>
      <nav class="flex-1 px-4 py-4 space-y-2">
        <?php require __DIR__ . '/nav.php'; ?>
      </nav>
      <div class="border-t border-gray-200 p-3 text-xs text-gray-500">
        <?php if ($u = current_user()): ?>
          <div class="flex items-center justify-between">
            <span><?= htmlspecialchars($u['name']) ?> (<?= htmlspecialchars($u['role']) ?>)</span>
            <a class="text-[var(--primary-color)]" href="<?= page_url('logout') ?>">Logout</a>
          </div>
        <?php endif; ?>
      </div>
    </aside>
    <main class="flex-1">
