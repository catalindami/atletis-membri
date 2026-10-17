<?php use App\Csrf; ?>
<div class="auth-card">
    <div class="auth-card__head">
        <span class="brand__mark brand__mark--lg" aria-hidden="true">A</span>
        <h1>Configurare inițială</h1>
        <p class="muted">Creează contul de administrator. Pagina se dezactivează automat după acest pas.</p>
    </div>
    <form method="post" action="<?= e(url('setup', ['token' => $token])) ?>" class="stack">
        <?= Csrf::field() ?>
        <label class="field <?= isset($errors['email']) ? 'has-error' : '' ?>">
            <span>Email administrator</span>
            <input type="email" name="email" value="<?= e($email) ?>" required autocomplete="username">
            <?php if (isset($errors['email'])): ?><small class="error"><?= e($errors['email']) ?></small><?php endif; ?>
        </label>
        <label class="field <?= isset($errors['password']) ? 'has-error' : '' ?>">
            <span>Parolă (minim 12 caractere, litere și cifre)</span>
            <input type="password" name="password" required minlength="12" autocomplete="new-password">
            <?php if (isset($errors['password'])): ?><small class="error"><?= e($errors['password']) ?></small><?php endif; ?>
        </label>
        <label class="field <?= isset($errors['password_confirm']) ? 'has-error' : '' ?>">
            <span>Repetă parola</span>
            <input type="password" name="password_confirm" required minlength="12" autocomplete="new-password">
            <?php if (isset($errors['password_confirm'])): ?><small class="error"><?= e($errors['password_confirm']) ?></small><?php endif; ?>
        </label>
        <button type="submit" class="btn btn--primary btn--block">Creează contul</button>
    </form>
</div>
