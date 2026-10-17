<?php
use App\Auth;
use App\Csrf;

try {
    $currentUser = Auth::user();
} catch (\Throwable) {
    $currentUser = null;
}
$route = (string) ($_GET['r'] ?? 'dashboard');
$appName = (string) config('app.name', 'Membri');
?><!doctype html>
<html lang="ro">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title><?= e(($title ?? '') !== '' ? $title . ' · ' . $appName : $appName) ?></title>
    <link rel="stylesheet" href="assets/app.css?v=2">
    <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'%3E%3Crect width='32' height='32' rx='7' fill='%23c2410c'/%3E%3Cpath d='M9 23l7-14 7 14' stroke='white' stroke-width='3' fill='none' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E">
</head>
<body class="<?= $currentUser ? 'is-app' : 'is-guest' ?>">
<?php if ($currentUser): ?>
<header class="topbar">
    <div class="topbar__inner">
        <a class="brand" href="<?= e(url('dashboard')) ?>">
            <span class="brand__mark" aria-hidden="true">A</span>
            <span class="brand__name"><?= e($appName) ?></span>
        </a>
        <nav class="nav" aria-label="Meniu principal">
            <a href="<?= e(url('dashboard')) ?>" class="<?= $route === 'dashboard' ? 'is-active' : '' ?>">Panou</a>
            <a href="<?= e(url('members')) ?>" class="<?= in_array($route, ['members', 'member'], true) ? 'is-active' : '' ?>">Membri</a>
            <a href="<?= e(url('member')) ?>" class="nav__cta">+ Membru nou</a>
        </nav>
        <div class="account">
            <a href="<?= e(url('password')) ?>" class="account__email" title="Schimbă parola"><?= e($currentUser['email']) ?></a>
            <form method="post" action="<?= e(url('logout')) ?>">
                <?= Csrf::field() ?>
                <button type="submit" class="link-button">Ieșire</button>
            </form>
        </div>
    </div>
</header>
<?php endif; ?>

<main class="<?= $currentUser ? 'page' : 'guest' ?>">
    <?php foreach ($flashes ?? [] as $f): ?>
        <div class="alert alert--<?= e($f['type']) ?>" role="status"><?= e($f['message']) ?></div>
    <?php endforeach; ?>
    <?= $content ?>
</main>
</body>
</html>
