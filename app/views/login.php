<?php use App\Csrf; ?>
<div class="auth-card">
    <div class="auth-card__head">
        <span class="brand__mark brand__mark--lg" aria-hidden="true">A</span>
        <h1><?= e(config('app.name', 'Membri')) ?></h1>
        <p class="muted">Autentificare administrator</p>
    </div>
    <?php if ($error): ?>
        <div class="alert alert--error" role="alert"><?= e($error) ?></div>
    <?php endif; ?>
    <form method="post" action="<?= e(url('login')) ?>" class="stack">
        <?= Csrf::field() ?>
        <label class="field">
            <span>Email</span>
            <input type="email" name="email" value="<?= e($email) ?>" required autofocus autocomplete="username">
        </label>
        <label class="field">
            <span>Parolă</span>
            <input type="password" name="password" required autocomplete="current-password">
        </label>
        <button type="submit" class="btn btn--primary btn--block">Intră în cont</button>
    </form>
</div>
