<?php use App\Csrf; ?>
<div class="page-head">
    <h1>Schimbă parola</h1>
</div>
<form method="post" action="<?= e(url('password')) ?>" class="card stack narrow">
    <?= Csrf::field() ?>
    <?php foreach ([
        'current_password'     => ['Parola actuală', 'current-password'],
        'new_password'         => ['Parola nouă (minim 12 caractere, litere și cifre)', 'new-password'],
        'new_password_confirm' => ['Repetă parola nouă', 'new-password'],
    ] as $name => [$label, $ac]): ?>
        <label class="field <?= isset($errors[$name]) ? 'has-error' : '' ?>">
            <span><?= e($label) ?></span>
            <input type="password" name="<?= e($name) ?>" required autocomplete="<?= e($ac) ?>">
            <?php if (isset($errors[$name])): ?><small class="error"><?= e($errors[$name]) ?></small><?php endif; ?>
        </label>
    <?php endforeach; ?>
    <div class="actions">
        <button type="submit" class="btn btn--primary">Salvează parola</button>
        <a href="<?= e(url('dashboard')) ?>" class="btn">Renunță</a>
    </div>
</form>
